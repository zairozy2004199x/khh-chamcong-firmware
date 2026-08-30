<?php
/**
 * KIỂM TRANG NỘI BỘ (wordpress/vhcp-noi-bo) — bảng tin trao đổi của công ty.
 *
 * 🔴 VÌ SAO PHẢI KIỂM RIÊNG. Trang này mở một CỬA THỨ HAI dùng chung thẻ phiên với hệ chấm công,
 * và là trang duy nhất trong cả hệ cho người dùng GÕ CHỮ RỒI HIỆN LẠI CHO NGƯỜI KHÁC ĐỌC. Hai
 * việc đó là hai chỗ hỏng đắt nhất:
 *
 *   1. Chữ của người này hiện trên màn người khác — sót một lần thoát chuỗi là một người gõ
 *      `<script>` vào bài rồi 240 người chạy đoạn mã đó, kèm theo cả thẻ phiên chấm công.
 *   2. Biểu mẫu POST không có chữ ký — trang ngoài dựng một cái nút, người trong công ty bấm
 *      vào là xoá bài / thả tim / đăng bài mang tên mình mà không biết.
 *
 * Nên bài này canh nặng nhất vào hai chỗ ấy, rồi mới tới phần đếm.
 *
 * Chạy: php tools/test/kiem-noi-bo.php
 */

$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';

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
	$truot[] = $ten . ( null === $them ? '' : "\n      → " . ( is_scalar( $them ) ? $them : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) );
}
function teq( $ten, $mong, $thuc ) {
	t( $ten . ' (mong ' . json_encode( $mong, JSON_UNESCAPED_UNICODE ) . ')', $mong === $thuc, $thuc );
}

/**
 * CẮT BỎ KHỐI "CHAT MINI" trước khi soi "trang không có script lạ nào".
 *
 * Anh Thắng 30/08/2026: *"bổ sung tab chat mini bên dưới để chat với thành viên"*, chốt chạy
 * kiểu TỰ CẬP NHẬT — nên `class-vhnb-trang.php::chat_mini()` là chỗ DUY NHẤT của cả trang được
 * phép có `<script>`. Mọi phép thử "không script nào lọt ra" trong bài này (viết từ trước khi
 * chat mini tồn tại) phải soi phần TRANG CÒN LẠI sau khi cắt bỏ đúng khối ấy, không phải chối
 * bỏ luôn tính năng người ta vừa xin.
 *
 * ⚠️ CẮT THEO MỐC `<!-- vhnb-chat -->` / `<!-- /vhnb-chat -->`, không đoán vị trí bằng cách khác.
 *    Hai mốc này do chính `chat_mini()` in ra bọc quanh toàn bộ khối của nó (HTML + script) —
 *    đây là hợp đồng giữa mã nguồn và bài kiểm; đổi tên/bỏ mốc ở bên kia thì hàm dưới đây không
 *    cắt được gì và VÔ TÌNH LÀM CÁC PHÉP THỬ "KHÔNG SCRIPT" HOÁ RA KHÔNG CANH GÌ CẢ — nên nếu
 *    một ngày hàm trả về y nguyên chuỗi đưa vào (không tìm thấy mốc), phải coi đó là bài kiểm
 *    ĐANG HỎNG, không phải trang đã sạch.
 */
function vhnb_bo_chat( $h ) {
	$dau = '<!-- vhnb-chat -->';
	$cuoi = '<!-- /vhnb-chat -->';
	$p = strpos( $h, $dau );
	if ( false === $p ) { return $h; }
	$q = strpos( $h, $cuoi, $p );
	if ( false === $q ) { return $h; }
	return substr( $h, 0, $p ) . substr( $h, $q + strlen( $cuoi ) );
}

/* ================================================================= nạp plugin nội bộ */

define( 'VHNB_VERSION', 'test' );
define( 'VHNB_DIR', $goc . '/wordpress/vhcp-noi-bo/' );

/* ĐỌC danh sách lớp từ CHÍNH tệp plugin, không gõ tay lại — thêm một lớp vào plugin mà bài kiểm
   không nạp là lỗi của BÀI KIỂM trông y như lỗi của plugin. Cùng lối với `vhcc_test_boot`. */
$chinh = file_get_contents( VHNB_DIR . 'vhcp-noi-bo.php' );
preg_match_all( "#require_once VHNB_DIR \. '(includes/class-vhnb-[a-z-]+\.php)';#", $chinh, $m_lop );
t( 'đọc được danh sách lớp trong vhcp-noi-bo.php', count( $m_lop[1] ) >= 3, $m_lop[1] );
foreach ( $m_lop[1] as $duong ) { require_once VHNB_DIR . $duong; }

function vhnb_dung_bang() {
	global $wpdb;
	foreach ( VHNB_DB::bang() as $ten => $than ) {
		$wpdb->exec_raw( 'DROP TABLE IF EXISTS ' . VHNB_DB::t( $ten ) );
		$wpdb->exec_raw( vhcc_test_ddl( VHNB_DB::t( $ten ), $than ) );
	}
}
vhnb_dung_bang();

/* ======================================================= CHƯA CÀI plugin chấm công
   🔴 Phần này PHẢI chạy TRƯỚC khi nạp plugin chấm công — nạp rồi thì `class_exists` trả true
   mãi mãi, không dựng lại được cảnh "thiếu plugin" nữa. Và đây đúng là cảnh của lần cài đầu:
   người ta tải plugin nội bộ lên trước, plugin chấm công sau. */

teq( 'chưa cài chấm công -> co_he_cham_cong = false', false, VHNB_Trang::co_he_cham_cong() );
teq( 'chưa cài chấm công -> the_phien rỗng, KHÔNG ném Error', '', VHNB_Trang::the_phien() );
teq( 'chưa cài chấm công -> toi() trả null', null, VHNB_Trang::toi() );

ob_start(); VHNB_Trang::ve( null ); $h0 = ob_get_clean();
t( 'thiếu plugin chấm công thì trang vẫn vẽ được, không trắng trang', strlen( $h0 ) > 300, strlen( $h0 ) );
t( 'và nói THẲNG là chưa cài plugin Chấm Công', false !== strpos( $h0, 'Chưa cài plugin Chấm Công' ) );
t( 'không dựng liên kết đăng nhập trỏ vào chỗ không có', false === strpos( $h0, 'Tới trang đăng nhập' ) );

/* ================================================================= nạp hệ chấm công */

vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );
vhcc_test_boot( $goc . '/wordpress/vhcp-cham-cong' );

teq( 'cài rồi -> co_he_cham_cong = true', true, VHNB_Trang::co_he_cham_cong() );

/* 🔴 GHIM ĐỒNG HỒ NGAY TỪ ĐẦU. Bảng tin xếp theo `tao_luc`, nên bài nào đăng lúc chưa ghim đồng
   hồ sẽ mang giờ THẬT của máy chạy bài kiểm — và giờ thật thì lúc sớm lúc muộn hơn mấy mốc giả
   ở dưới. Phép thử "bài mới nhất nằm trên" khi ấy xanh hay đỏ tuỳ vào lúc chạy là mấy giờ. */
vhcp_test_dat_gio( '2026-08-20 08:00:00' );

global $wpdb;

/* Ba người: một nhân viên, một cửa hàng trưởng, một admin. */
$TOK_NV  = VHCC_Auth::phat_token( 'Trần Văn A', 'Nhân viên',       'CS_VIVO', 'NV001' );
$TOK_CHT = VHCC_Auth::phat_token( 'Lê Thị B',   'Cửa hàng trưởng', 'CS_VIVO', 'NV002' );
$TOK_AD  = VHCC_Auth::phat_token( 'Nguyễn C',   'Admin',           '',        'AD001' );
$U_NV  = VHCC_Auth::user_by_token( $TOK_NV );
$U_CHT = VHCC_Auth::user_by_token( $TOK_CHT );
$U_AD  = VHCC_Auth::user_by_token( $TOK_AD );
$TOK_LA = VHCC_Auth::phat_token( 'Phạm Thị D', 'Nhân viên', 'CS_AEON', 'NV009' );
$U_NV_LA = VHCC_Auth::user_by_token( $TOK_LA );
t( 'dựng được ba phiên', $U_NV && $U_CHT && $U_AD );
teq( 'nhân viên KHÔNG phải admin của trang nội bộ', false, VHNB_Bai::la_admin( $U_NV ) );
teq( 'cửa hàng trưởng cũng KHÔNG', false, VHNB_Bai::la_admin( $U_CHT ) );
teq( 'admin thì có', true, VHNB_Bai::la_admin( $U_AD ) );

/* ================================================================= đăng bài */

$r = VHNB_Bai::dang( array(), 'xin chào' );
teq( 'chưa đăng nhập thì không đăng được', false, $r['ok'] );

$r = VHNB_Bai::dang( $U_NV, "   \n\n  " );
teq( 'bài rỗng (toàn khoảng trắng) bị chối', false, $r['ok'] );
t( 'và nói rõ vì sao', false !== strpos( $r['error'], 'rỗng' ), $r['error'] );

$r = VHNB_Bai::dang( $U_NV, 'Chào cả nhà, hôm nay cơ sở VIVO đông khách.' );
t( 'đăng được bài', ! empty( $r['ok'] ), $r );
$B1 = (int) $r['id'];
t( 'trả về id thật', $B1 > 0, $B1 );

$bai = $wpdb->get_row( 'SELECT * FROM ' . VHNB_DB::t( 'bai' ) . ' WHERE id=' . $B1, ARRAY_A );
teq( 'tên người đăng lấy TỪ PHIÊN', 'Trần Văn A', $bai['ho_ten'] );
teq( 'mã NV lấy TỪ PHIÊN', 'NV001', $bai['ma_nv'] );
teq( 'bài không khai nhóm thì là bài chung', '', $bai['nhom'] );
teq( 'bài mới chưa ghim', 0, (int) $bai['ghim'] );

/* 🔴 KHÔNG cho đăng thay người khác. `dang()` chỉ nhận nội dung và nhóm — không có tham số nào
   nhận tên/mã, nên biểu mẫu có gửi `ho_ten=Giám đốc` cũng không tới đâu. Canh bằng chữ ký hàm,
   vì đó là thứ chặn thật; canh bằng một lượt gọi thì chỉ chặn được đúng lượt gọi đó. */
$rf = new ReflectionMethod( 'VHNB_Bai', 'dang' );
$ten_ts = array();
foreach ( $rf->getParameters() as $p ) { $ten_ts[] = $p->getName(); }
teq( 'dang() KHÔNG có tham số nào nhận tên/mã người đăng',
	array( 'u', 'noi_dung', 'nhom', 'nhom_id', 'anh' ), $ten_ts );

/* Bài dài quá thì cắt, không nhận nguyên. */
$r = VHNB_Bai::dang( $U_CHT, str_repeat( 'x', VHNB_Bai::DAI_TOI_DA + 500 ) );
$dai = $wpdb->get_var( 'SELECT LENGTH(noi_dung) FROM ' . VHNB_DB::t( 'bai' ) . ' WHERE id=' . (int) $r['id'] );
teq( 'bài dài quá bị cắt đúng mức', VHNB_Bai::DAI_TOI_DA, (int) $dai );
$wpdb->delete( VHNB_DB::t( 'bai' ), array( 'id' => (int) $r['id'] ) );

/* ================================================================= bình luận */

$r = VHNB_Bai::binh_luan( $U_CHT, $B1, '' );
teq( 'bình luận rỗng bị chối', false, $r['ok'] );
$r = VHNB_Bai::binh_luan( $U_CHT, 999999, 'bài không tồn tại' );
teq( 'bình luận vào bài không tồn tại bị chối', false, $r['ok'] );

t( 'bình luận được', ! empty( VHNB_Bai::binh_luan( $U_CHT, $B1, 'Đúng rồi ạ' )['ok'] ) );
t( 'người khác cũng bình luận được', ! empty( VHNB_Bai::binh_luan( $U_AD, $B1, 'Tốt' )['ok'] ) );
teq( 'đếm bình luận đúng', 2,
	(int) $wpdb->get_var( 'SELECT so_bl FROM ' . VHNB_DB::t( 'bai' ) . ' WHERE id=' . $B1 ) );
teq( 'danh sách bình luận đủ 2', 2, count( VHNB_Bai::ds_binh_luan( $B1 ) ) );

/* ================================================================= thả tim */

$r = VHNB_Bai::tim( array( 'name' => 'Ai đó', 'ma_nv' => '' ), $B1 );
teq( 'người CHƯA CÓ mã NV thì không thả tim được', false, $r['ok'] );
t( 'và câu báo chỉ đúng chỗ cần sửa (hồ sơ)', false !== strpos( $r['error'], 'Mã NV' ), $r['error'] );

$r = VHNB_Bai::tim( $U_NV, $B1 );
teq( 'thả tim được', true, $r['da_tim'] );
teq( 'đếm tim = 1', 1, (int) $wpdb->get_var( 'SELECT so_tim FROM ' . VHNB_DB::t( 'bai' ) . ' WHERE id=' . $B1 ) );

/* 🔴 Bấm lại là BỎ tim, không phải thành hai tim. */
$r = VHNB_Bai::tim( $U_NV, $B1 );
teq( 'bấm lại là bỏ tim', false, $r['da_tim'] );
teq( 'đếm tim về 0', 0, (int) $wpdb->get_var( 'SELECT so_tim FROM ' . VHNB_DB::t( 'bai' ) . ' WHERE id=' . $B1 ) );

VHNB_Bai::tim( $U_NV, $B1 );
VHNB_Bai::tim( $U_CHT, $B1 );
VHNB_Bai::tim( $U_AD, $B1 );
teq( 'ba người ba tim', 3, (int) $wpdb->get_var( 'SELECT so_tim FROM ' . VHNB_DB::t( 'bai' ) . ' WHERE id=' . $B1 ) );

/* 🔴 Khoá UNIQUE (bai_id, ma_nv) là thứ giữ cho con số có nghĩa. Chèn thẳng vào bảng để chắc
   khoá ấy CÓ THẬT trên bảng — không phải chỉ có trong lời khai. */
$chan = false;
try {
	$r_tim = $wpdb->insert( VHNB_DB::t( 'tim' ),
		array( 'bai_id' => $B1, 'ma_nv' => 'NV001', 'tao_luc' => current_time( 'mysql' ) ) );
	/* `$wpdb` thật KHÔNG ném: nó trả `false` rồi đặt `last_error`. Bản giả nay chạy đúng lối
	   ấy (trước kia nó để PDO ném, và vì thế mọi nhánh "cơ sở dữ liệu hỏng" của mã thật không
	   thử được — xem chú thích ở `wp-stub.php`). Vẫn giữ `try` để phép thử đúng với CẢ HAI cách
	   báo lỗi: thứ đang kiểm là RÀNG BUỘC CỦA BẢNG, không phải cách thư viện báo lỗi. */
	if ( false === $r_tim ) { $chan = true; }
} catch ( Exception $e ) {
	$chan = true;
}
teq( '🔴 khoá UNIQUE chặn một người thả tim hai lần', 3,
	(int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHNB_DB::t( 'tim' ) . ' WHERE bai_id=' . $B1 ) );
t( 'và ràng buộc đó CÓ THẬT trên bảng, không phải chỉ có trong lời khai', $chan );

$da = VHNB_Bai::da_tim( $U_NV, array( array( 'id' => $B1 ) ) );
t( 'da_tim() biết người này đã thả tim bài này', isset( $da[ $B1 ] ), $da );
$da2 = VHNB_Bai::da_tim( array( 'name' => 'x', 'ma_nv' => 'NV999' ), array( array( 'id' => $B1 ) ) );
teq( 'người chưa thả thì không', array(), $da2 );
teq( 'da_tim() với danh sách rỗng trả mảng rỗng, không đi hỏi kho', array(), VHNB_Bai::da_tim( $U_NV, array() ) );

/* 🔴 dem_lai() phải ĐẾM LẠI, không cộng dồn: bẻ con số cho lệch rồi gọi lại, nó phải tự chữa. */
$wpdb->update( VHNB_DB::t( 'bai' ), array( 'so_tim' => 99, 'so_bl' => 77 ), array( 'id' => $B1 ) );
VHNB_Bai::dem_lai( $B1 );
$sau = $wpdb->get_row( 'SELECT so_tim, so_bl FROM ' . VHNB_DB::t( 'bai' ) . ' WHERE id=' . $B1, ARRAY_A );
teq( 'dem_lai tự chữa con số tim đã lệch', 3, (int) $sau['so_tim'] );
teq( 'dem_lai tự chữa con số bình luận đã lệch', 2, (int) $sau['so_bl'] );

/* ================================================================= ai xoá được bài */

$bai1 = $wpdb->get_row( 'SELECT * FROM ' . VHNB_DB::t( 'bai' ) . ' WHERE id=' . $B1, ARRAY_A );
teq( 'tác giả xoá được bài của mình', true,  VHNB_Bai::duoc_xoa( $U_NV,  $bai1 ) );
teq( 'người khác KHÔNG xoá được', false, VHNB_Bai::duoc_xoa( $U_CHT, $bai1 ) );
teq( 'admin xoá được mọi bài', true,  VHNB_Bai::duoc_xoa( $U_AD,  $bai1 ) );

/* 🔴 So bằng MÃ, không bằng TÊN. Trong 240 người có trùng tên, mà bài của người khác thì không
   ai được xoá — kể cả người trùng tên. */
$trung_ten = array( 'name' => 'Trần Văn A', 'role' => 'Nhân viên', 'ma_nv' => 'NV777' );
teq( 'người TRÙNG TÊN nhưng khác mã thì KHÔNG xoá được', false, VHNB_Bai::duoc_xoa( $trung_ten, $bai1 ) );

/* Bài của người mã rỗng: lui về so tên, và chỉ so với bài cũng mã rỗng. */
$bai_ro = array( 'ma_nv' => '', 'ho_ten' => 'Người Cũ' );
teq( 'mã rỗng + tên khớp -> xoá được', true,
	VHNB_Bai::duoc_xoa( array( 'name' => 'Người Cũ', 'ma_nv' => '' ), $bai_ro ) );
teq( 'mã rỗng + tên khác -> không', false,
	VHNB_Bai::duoc_xoa( array( 'name' => 'Người Khác', 'ma_nv' => '' ), $bai_ro ) );
teq( 'người CÓ mã không xoá được bài mã rỗng chỉ vì trùng tên', false,
	VHNB_Bai::duoc_xoa( array( 'name' => 'Người Cũ', 'ma_nv' => 'NV555' ), $bai_ro ) );

$r = VHNB_Bai::xoa( $U_CHT, $B1 );
teq( 'người khác gọi xoa() cũng bị chối ở tầng lõi, không chỉ ở nút', false, $r['ok'] );

/* ================================================================= ghim */

$r = VHNB_Bai::ghim( $U_CHT, $B1, true );
teq( 'cửa hàng trưởng KHÔNG ghim được', false, $r['ok'] );
teq( 'bài vẫn chưa ghim', 0, (int) $wpdb->get_var( 'SELECT ghim FROM ' . VHNB_DB::t( 'bai' ) . ' WHERE id=' . $B1 ) );

/* ================================================================= bảng tin, ghim, lọc nhóm */

vhcp_test_dat_gio( '2026-08-26 08:00:00' );
$r_chung = VHNB_Bai::dang( $U_AD, 'Thông báo toàn công ty: nghỉ lễ 2/9.', '' );
vhcp_test_dat_gio( '2026-08-26 09:00:00' );
$r_vp    = VHNB_Bai::dang( $U_AD, 'Văn phòng họp 15h.', 'Văn phòng' );
vhcp_test_dat_gio( '2026-08-26 10:00:00' );
$r_kvc   = VHNB_Bai::dang( $U_CHT, 'Khu vui chơi cần thêm người trực.', 'Khu vui chơi' );

$ds = VHNB_Bai::bang_tin( '' );
t( 'bảng tin thấy hết bài', count( $ds ) >= 4, count( $ds ) );
teq( 'không ghim thì bài MỚI NHẤT nằm trên', (int) $r_kvc['id'], (int) $ds[0]['id'] );

VHNB_Bai::ghim( $U_AD, $r_chung['id'], true );
$ds = VHNB_Bai::bang_tin( '' );
teq( '🔴 bài GHIM nằm trên cùng, dù cũ nhất', (int) $r_chung['id'], (int) $ds[0]['id'] );

/* 🔴 Ghim phải có tác dụng ở CẢ HAI nhánh truy vấn — xem tất cả VÀ lọc bộ phận. Đây là hai câu
   SQL viết rời nhau, nên bỏ `ghim DESC` ở một câu thì câu kia vẫn xanh: phá thử bỏ `ghim DESC`
   riêng nhánh lọc nhóm, không một chốt nào đỏ. Nay canh cả hai. */
/* ⚠️ Phải ghim đúng bài CŨ NHẤT trong danh sách lọc thì chốt mới có nghĩa. Bản đầu em ghim bài
   MỚI hơn — nó nằm trên dù có `ghim DESC` hay không, nên chốt xanh cả khi đã phá. */
$ds_g = VHNB_Bai::bang_tin( 'Văn phòng' );
teq( '🔴 lọc bộ phận thì bài ghim vẫn nằm trên, dù nó cũ nhất',
	(int) $r_chung['id'], (int) $ds_g[0]['id'] );

/* 🔴 Lọc bộ phận vẫn PHẢI thấy bài chung. Thông báo toàn công ty mà biến mất vì đang lọc bộ
   phận thì lọc xong là bỏ sót đúng thứ quan trọng nhất. */
$ds_vp = VHNB_Bai::bang_tin( 'Văn phòng' );
$id_vp = array();
foreach ( $ds_vp as $b ) { $id_vp[] = (int) $b['id']; }
t( 'lọc Văn phòng vẫn thấy bài chung', in_array( (int) $r_chung['id'], $id_vp, true ), $id_vp );
t( 'lọc Văn phòng thấy bài Văn phòng', in_array( (int) $r_vp['id'], $id_vp, true ), $id_vp );
t( 'lọc Văn phòng KHÔNG thấy bài Khu vui chơi', ! in_array( (int) $r_kvc['id'], $id_vp, true ), $id_vp );

/* Xoá bài thì dọn sạch bình luận và tim của nó — để lại là rác không ai với tới được. */
$r = VHNB_Bai::xoa( $U_AD, $B1 );
t( 'admin xoá được bài của người khác', ! empty( $r['ok'] ), $r );
teq( 'xoá bài thì bình luận của nó cũng đi', 0,
	(int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHNB_DB::t( 'binh_luan' ) . ' WHERE bai_id=' . $B1 ) );
teq( 'và tim cũng đi', 0,
	(int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHNB_DB::t( 'tim' ) . ' WHERE bai_id=' . $B1 ) );
teq( 'xoá bài đã xoá thì báo không còn, không chết', false, VHNB_Bai::xoa( $U_AD, $B1 )['ok'] );

/* ================================================================= phân trang */

vhnb_dung_bang();
for ( $i = 1; $i <= VHNB_Bai::MOI_TRANG + 3; $i++ ) {
	vhcp_test_dat_gio( sprintf( '2026-08-%02d 08:00:00', min( 28, $i ) ) );
	VHNB_Bai::dang( $U_NV, 'bài số ' . $i );
}
teq( 'trang 1 đúng ' . VHNB_Bai::MOI_TRANG . ' bài', VHNB_Bai::MOI_TRANG, count( VHNB_Bai::bang_tin( '', 1 ) ) );
teq( 'trang 2 còn 3 bài', 3, count( VHNB_Bai::bang_tin( '', 2 ) ) );
teq( 'trang 0 coi như trang 1, không âm offset', VHNB_Bai::MOI_TRANG, count( VHNB_Bai::bang_tin( '', 0 ) ) );
teq( 'trang quá xa thì rỗng, không chết', 0, count( VHNB_Bai::bang_tin( '', 99 ) ) );

/* ================================================================= chuẩn hoá chữ */

teq( 'gon() bỏ khoảng trắng hai đầu', 'a b', VHNB_Bai::gon( "  a b \n ", 100 ) );
teq( 'gon() đổi CRLF của Windows thành xuống dòng thường', "a\nb", VHNB_Bai::gon( "a\r\nb", 100 ) );
teq( 'gon() đổi CR đơn của Mac cũ thành xuống dòng thường', "a\nb", VHNB_Bai::gon( "a\rb", 100 ) );
/* Người ta hay bấm Enter chục cái để "đẩy bài lên" — gộp lại còn tối đa hai dòng trống. */
teq( 'gon() gộp một loạt dòng trống', "a\n\n\nb", VHNB_Bai::gon( "a\n\n\n\n\n\n\nb", 100 ) );
/* 🔴 Cắt theo KÝ TỰ, không theo byte: cắt giữa một chữ có dấu là ra một byte què, trình duyệt
   hiện ra dấu hỏi đen. Một chữ tiếng Việt có dấu là 3 byte. */
teq( 'gon() cắt theo ký tự chứ không theo byte', 'ườ', VHNB_Bai::gon( 'ườ ng', 2 ) );
teq( 'chuan_nhom() cắt tên nhóm quá dài', 60,
	function_exists( 'mb_strlen' ) ? mb_strlen( VHNB_Bai::chuan_nhom( str_repeat( 'ố', 200 ) ), 'UTF-8' ) : 60 );

vhcp_test_dat_gio( '2026-08-26 12:00:00' );
teq( 'bao_lau: vừa xong', 'vừa xong',      VHNB_Bai::bao_lau( '2026-08-26 11:59:30' ) );
teq( 'bao_lau: phút',     '5 phút trước',  VHNB_Bai::bao_lau( '2026-08-26 11:55:00' ) );
teq( 'bao_lau: giờ',      '3 giờ trước',   VHNB_Bai::bao_lau( '2026-08-26 09:00:00' ) );
teq( 'bao_lau: ngày',     '2 ngày trước',  VHNB_Bai::bao_lau( '2026-08-24 12:00:00' ) );
teq( 'bao_lau: quá tuần thì ghi ngày tháng', '01/08/2026', VHNB_Bai::bao_lau( '2026-08-01 12:00:00' ) );
teq( 'bao_lau: giờ hỏng thì trả rỗng, không ghi 01/01/1970', '', VHNB_Bai::bao_lau( 'không phải giờ' ) );

/* ================================================================= vẽ trang */

vhnb_dung_bang();
$_GET = array(); $_POST = array(); $_COOKIE = array();

/* ==================================================================================
   PHẦN CÔNG KHAI — thứ người CHƯA đăng nhập nhìn thấy trên trang chủ

   Anh Thắng 30/08/2026: *"cho trang này là trang chủ luôn, nhân viên đăng nhập vào sẽ thấy
   trang này"*, rồi nói rõ thêm: *"thấy trang chủ, nhưng thông tin chung, còn thông tin nội bộ
   thì phải đăng nhập, thông tin chung như hướng dẫn sử dụng chẳng hạn"*.
   ================================================================================== */
ob_start(); VHNB_Trang::ve( null ); $h_ra = ob_get_clean();
/* 🔴 Ô PIN NẰM NGAY TRÊN TRANG NÀY — anh Thắng 30/08/2026: *"Đăng nhập 1 trang nội bộ là dùng
   được tất cả các trang"*. Trang này là trang chủ; bắt người ta bấm sang trang chấm công, gõ
   PIN ở đó, rồi tự tìm đường quay lại là ba bước cho một việc.

   ⚠️ PHÉP THỬ CŨ Ở ĐÂY ĐÒI NGƯỢC LẠI — *"KHÔNG hỏi PIN ở đây (một cửa PIN thôi)"*. Nó hiểu sai
      chữ MỘT CỬA: một cửa nghĩa là một BỘ CHỐT (một bộ đếm nhập sai, một cái khoá 10 phút, một
      cách phát thẻ), không phải một CHỖ ĐẶT Ô NHẬP. Chốt thật nằm ở phép thử ngay dưới: ô này
      phải đi qua đúng `VHCC_Auth::login()`. */
t( 'chưa đăng nhập -> có Ô GÕ PIN ngay tại trang này',
	false !== strpos( $h_ra, 'name="pin"' )
	&& false !== strpos( $h_ra, 'name="viec" value="dang_nhap"' ), $h_ra );
t( 'và KHÔNG có ô đăng bài', false === strpos( $h_ra, 'name="noi_dung"' ) );
/* 🔴 PIN KHÔNG BAO GIỜ HIỆN LÊN MÀN HÌNH — trang chạy ngoài internet, ảnh chụp đi khắp nơi. */
t( 'ô PIN là type=password, không phải ô chữ thường',
	false !== strpos( $h_ra, 'type="password" name="pin"' ), $h_ra );
t( 'vẫn còn đường sang trang chấm công để lấy lại PIN quên',
	false !== strpos( $h_ra, 'trang chấm công</a>' ), $h_ra );

/* 🔴 THÔNG TIN CHUNG THÌ CÓ. Bản trước chỉ có đúng một cái nút giữa màn hình trắng: người mới
   vào không biết mình đang ở đâu, không biết lấy PIN ở đâu, và không có gì đọc trong lúc chưa
   có PIN. */
t( '🔴 có khối Hướng dẫn sử dụng', false !== strpos( $h_ra, 'Hướng dẫn sử dụng' ), $h_ra );
t( 'mỗi dòng người khai thành một gạch đầu dòng',
	preg_match( '/<li[^>]*>Đăng nhập bằng mã PIN chấm công\.<\/li>/', $h_ra ) === 1, $h_ra );
t( 'có lời chào giới thiệu trang', false !== strpos( $h_ra, 'Trang dùng chung của người nhà' ), $h_ra );
t( 'và đường sang trang Chấm công', false !== strpos( $h_ra, 'Chấm công</a>' ), $h_ra );

/* 🔴 KHÔNG MỘT DÒNG NÀO CỦA NỘI BỘ LỌT RA TRANG CHỦ CÔNG KHAI.
   Đây là trang bất kỳ ai trên internet cũng mở được, và công cụ tìm kiếm cũng đọc được. Dựng
   sẵn một bài đăng có tên người, một nhóm có tên, rồi soi xem có rò gì không. */
/* ⚠️ DỌN LẠI SAU KHI SOI. Bài dựng thêm ở đây làm lệch mọi phép ĐẾM của các khối phía sau
   ("admin thấy nút Xoá ở MỌI bài" đếm đúng số bài) — cái bẫy thứ tự khối thử đã cắn nhiều lần
   trong dự án này. */
$id_ro = VHNB_Bai::dang( $U_AD, 'Bài mật chỉ người nhà đọc' );
ob_start(); VHNB_Trang::ve( null ); $h_ra2 = ob_get_clean();
t( '🔴 nội dung bài KHÔNG lọt ra ngoài',
	false === strpos( $h_ra2, 'Bài mật chỉ người nhà đọc' ), $h_ra2 );
t( '🔴 tên người đăng cũng không',
	false === strpos( $h_ra2, (string) $U_AD['name'] ), $h_ra2 );
t( 'không có ô bình luận', false === strpos( $h_ra2, 'name="binh_luan"' ), $h_ra2 );
t( 'không có cột nhóm', false === strpos( $h_ra2, 'NHÓM CỦA TÔI' ), $h_ra2 );
VHNB_Bai::xoa( $U_AD, is_array( $id_ro ) ? (int) ( isset( $id_ro['id'] ) ? $id_ro['id'] : 0 ) : (int) $id_ro );

/* Khai lại lời chào và hướng dẫn — cả hai phải ăn ngay ra trang. */
update_option( 'vhnb_loi_chao', 'Chào bà con K&H' );
update_option( 'vhnb_huong_dan', "Dòng một\nDòng hai" );
ob_start(); VHNB_Trang::ve( null ); $h_ra3 = ob_get_clean();
t( 'lời chào khai tay ăn ngay', false !== strpos( $h_ra3, 'Chào bà con K&amp;H' ), $h_ra3 );
t( 'hướng dẫn khai tay cũng vậy', false !== strpos( $h_ra3, '<li style="margin:0 0 7px">Dòng hai</li>' ), $h_ra3 );
t( 'và câu mặc định biến mất', false === strpos( $h_ra3, 'Chưa có PIN, hoặc quên PIN' ), $h_ra3 );

/* ⚠️ Ô này in ra trang chủ CÔNG KHAI. Một thẻ dán nhầm vào đây thì chạy trên máy mọi khách. */
update_option( 'vhnb_huong_dan', 'Bình thường<script>alert(1)</script>' );
ob_start(); VHNB_Trang::ve( null ); $h_xss = ob_get_clean();
t( '🔴 thẻ script trong ô hướng dẫn KHÔNG chạy được',
	false === strpos( $h_xss, '<script>alert' ), $h_xss );

/* Xoá hết rồi lưu = bỏ hẳn khối hướng dẫn, khác với "chưa khai bao giờ". */
update_option( 'vhnb_huong_dan', '' );
ob_start(); VHNB_Trang::ve( null ); $h_trong = ob_get_clean();
t( 'xoá hết thì bỏ hẳn khối hướng dẫn',
	false === strpos( $h_trong, 'Hướng dẫn sử dụng' ), $h_trong );
teq( 'nhưng chưa khai bao giờ thì vẫn có mặc định', VHNB_Trang::HD_MAC_DINH,
	( delete_option( 'vhnb_huong_dan' ) || true ) ? VHNB_Trang::huong_dan() : '' );
delete_option( 'vhnb_loi_chao' );

/* ---- DÙNG LÀM TRANG CHỦ ---- */
delete_option( 'vhnb_trang_chu' );
t( 'mặc định KHÔNG chiếm trang chủ', ! VHNB_Trang::lam_trang_chu() );
update_option( 'vhnb_trang_chu', 1 );
t( 'bật thì lam_trang_chu = true', VHNB_Trang::lam_trang_chu() );

/* 🔴 CHIẾM ĐÚNG TRANG CHỦ, KHÔNG CHIẾM GÌ KHÁC.
   `is_front_page()` là chốt duy nhất. Thiếu nó thì MỌI trang của site biến thành trang Nội bộ
   — kể cả trang của app khác — và người dùng không còn đường nào đi tiếp. Đây là loại hỏng
   không ai thử ra bằng mắt, vì trang chủ vẫn đúng. */
$GLOBALS['VHCP_QVAR'] = array();
$GLOBALS['VHCP_LA_ADMIN'] = 0;
$GLOBALS['VHCP_LA_TRANG_CHU'] = 1;
t( 'bật + đang ở trang chủ -> chiếm', VHNB_Trang::nen_ve() );
$GLOBALS['VHCP_LA_TRANG_CHU'] = 0;
t( '🔴 bật nhưng KHÔNG phải trang chủ -> KHÔNG chiếm', ! VHNB_Trang::nen_ve() );

/* ⚠️ `template_redirect` không chạy trong wp-admin, nhưng chốt vẫn phải có: bật nhầm thì cũng
   không bao giờ khoá được anh Thắng ra khỏi wp-admin. */
$GLOBALS['VHCP_LA_TRANG_CHU'] = 1;
$GLOBALS['VHCP_LA_ADMIN'] = 1;
t( '🔴 trong wp-admin thì KHÔNG chiếm', ! VHNB_Trang::nen_ve() );
$GLOBALS['VHCP_LA_ADMIN'] = 0;

/* Chưa bật thì trang chủ vẫn là trang chủ của người khác. */
delete_option( 'vhnb_trang_chu' );
t( 'chưa bật -> trang chủ không bị đụng tới', ! VHNB_Trang::nen_ve() );

/* Nhưng đường dẫn riêng `/noi-bo/` thì luôn chạy, bật hay không cũng vậy. */
$GLOBALS['VHCP_LA_TRANG_CHU'] = 0;
$GLOBALS['VHCP_QVAR'] = array( 'vhnb_trang' => 1 );
t( 'đường dẫn riêng luôn chạy dù chưa bật trang chủ', VHNB_Trang::nen_ve() );
$GLOBALS['VHCP_QVAR'] = array();
/* Đường dự phòng `?vhnb=1` — dùng khi site chưa bật đường dẫn tĩnh (permalink), lúc ấy luật
   `^noi-bo/?$` chưa có hiệu lực và đây là cửa DUY NHẤT vào trang. */
$_GET['vhnb'] = '1';
t( 'đường dự phòng ?vhnb=1 vẫn vào được', VHNB_Trang::nen_ve() );
unset( $_GET['vhnb'] );
t( 'bỏ tham số ra thì thôi', ! VHNB_Trang::nen_ve() );
delete_option( 'vhnb_trang_chu' );

$_COOKIE[ VHCC_Web::COOKIE ] = $TOK_NV;
VHNB_Bai::dang( $U_CHT, "Dòng một\nDòng hai", 'Văn phòng' );
/* ⚠️ Dấu NHÁY trong `onerror="…"` là cố ý. Bản đầu em viết `onerror=alert(1)` không nháy, và
   chốt "không có thuộc tính on*=" vẫn XANH cả khi đã bỏ `esc_html` — vì chốt ấy đòi có dấu
   nháy sau dấu bằng. Một vết phá thật mà chốt không đỏ thì chốt ấy chưa canh gì cả. */
$r_xau = VHNB_Bai::dang( $U_NV,
	'<script>alert(1)</script> và "><img src="x" onerror="alert(1)">' );
VHNB_Bai::binh_luan( $U_AD, $r_xau['id'], '<b>đậm?</b> & <script>x</script>' );

ob_start(); VHNB_Trang::ve( $U_NV ); $h = ob_get_clean();
t( 'đăng nhập rồi thì vẽ được bảng tin', strlen( $h ) > 1000, strlen( $h ) );
t( 'có ô đăng bài', false !== strpos( $h, 'name="noi_dung"' ) );
t( 'hiện tên người đang đăng nhập', false !== strpos( $h, 'Trần Văn A' ) );
t( 'có liên kết quay về trang chấm công', false !== strpos( $h, VHCC_Web::url() ) );

/* 🔴 THOÁT CHUỖI. Đây là phép thử đắt nhất của cả bài: một người gõ `<script>` vào bài, 240
   người mở bảng tin. Sót chỗ này là mất luôn thẻ phiên chấm công của cả công ty. */
t( '🔴 không có thẻ <script> nào lọt ra NGOÀI khối chat mini',
	false === stripos( vhnb_bo_chat( $h ), '<script' ), 'CÓ <script> TRONG TRANG' );
t( '🔴 không có thuộc tính on*= nào (kể cả của bài người dùng gõ)',
	! preg_match( '/\son[a-z]+\s*=\s*["\']/i', $h ), 'CÓ thuộc tính on*= trong trang' );
t( 'không có javascript: nào', false === stripos( $h, 'javascript:' ) );
t( 'bài chứa mã độc hiện ra dạng CHỮ', false !== strpos( $h, '&lt;script&gt;alert(1)&lt;/script&gt;' ) );
t( 'bình luận chứa thẻ cũng hiện ra dạng chữ', false !== strpos( $h, '&lt;b&gt;đậm?&lt;/b&gt;' ) );
/* Xuống dòng của người viết PHẢI giữ, nhưng bằng thẻ <br> THẬT — thoát TRƯỚC, nl2br SAU.
   Làm ngược lại là chữ "<br />" hiện lù lù giữa bài. */
t( 'xuống dòng giữ được bằng thẻ <br> thật', false !== strpos( $h, '<br' ) );
t( 'và KHÔNG hiện chữ "&lt;br', false === strpos( $h, '&lt;br' ) );

/* 🔴 CHỮ KÝ BIỂU MẪU. Không có nó thì một trang ngoài dựng nút "Bấm để nhận quà" là người trong
   công ty bấm phát xoá bài / đăng bài mang tên mình. Canh TỪNG biểu mẫu, không canh tổng số. */
$manh = explode( '<form ', $h );
array_shift( $manh );
t( 'trang có biểu mẫu để canh', count( $manh ) >= 4, count( $manh ) );
$thieu = 0;
foreach ( $manh as $f ) {
	$than = substr( $f, 0, strpos( $f, '</form>' ) === false ? strlen( $f ) : strpos( $f, '</form>' ) );
	if ( false === strpos( $than, 'name="ky"' ) ) { $thieu++; }
}
teq( '🔴 MỌI biểu mẫu POST đều mang chữ ký', 0, $thieu );

/* Nhân viên không được thấy nút ghim / nút xoá bài người khác. */
t( 'nhân viên KHÔNG thấy nút Ghim', false === strpos( $h, '📌 Ghim' ), 'nhân viên thấy nút Ghim' );
$so_xoa_nv = substr_count( $h, '>Xoá<' );
teq( 'nhân viên chỉ thấy nút Xoá ở ĐÚNG bài của mình', 1, $so_xoa_nv );

$_COOKIE[ VHCC_Web::COOKIE ] = $TOK_AD;
ob_start(); VHNB_Trang::ve( $U_AD ); $h_ad = ob_get_clean();
t( 'admin thấy nút Ghim', false !== strpos( $h_ad, '📌 Ghim' ) );
teq( 'admin thấy nút Xoá ở MỌI bài', 2, substr_count( $h_ad, '>Xoá<' ) );

/* Thanh nhóm phải lấy từ hệ chấm công, không bịa danh sách riêng. */
$nhom = VHNB_Trang::ds_nhom();
teq( 'danh sách nhóm lấy THẲNG từ bộ phận của hệ chấm công',
	array_values( (array) VHCC_Luong::BP_DS ), array_values( $nhom ) );
foreach ( $nhom as $x ) { t( 'thanh nhóm có ô "' . $x . '"', false !== strpos( $h_ad, esc_html( $x ) ) ); }

/* ================================================================= thông tin công ty cuối trang

   Anh Thắng 26/08: *"nhớ bổ sung thông tin công ty ở cuối trang nhé"*. Trang này KHÔNG giữ bản
   chép nào — nó gọi `VHCC_Cty` của plugin chấm công, mà plugin ấy lại đọc ô cài đặt dùng chung
   `vhg_chan`. Bốn trang, một kho. */

$_ct_trong = VHCC_Cty::html();
t( 'chưa khai công ty thì KHÔNG bịa ra tên / mã số thuế nào',
	false === strpos( $_ct_trong, 'cty-ten' ) && false === strpos( $_ct_trong, 'Mã số thuế' ),
	$_ct_trong );
/* ⚠️ Nhưng VẪN giữ nhãn phiên bản — anh Thắng 26/08: *"Cuối mỗi tất cả các trang bổ sung tên
   phiên bản đang chạy"*. Site mới cài thì thông tin công ty chắc chắn trống, mà đó lại đúng
   lúc cần biết bản nào đang chạy nhất. */
t( 'nhưng vẫn giữ nhãn phiên bản', false !== strpos( $_ct_trong, 'class="cty-pb"' ), $_ct_trong );
$_COOKIE[ VHCC_Web::COOKIE ] = $TOK_NV;
ob_start(); VHNB_Trang::ve( $U_NV ); $h_k = ob_get_clean();
t( 'và trang vẫn vẽ bình thường', strpos( $h_k, 'name="noi_dung"' ) !== false );

update_option( 'vhg_chan', array(
	'ten' => 'CÔNG TY TNHH DỊCH VỤ VÀ GIẢI TRÍ K&H', 'mst' => '0106924989',
	'dia_chi' => 'Thôn Mai Nội, Xã Sóc Sơn, Thành phố Hà Nội, Việt Nam',
	'dien_thoai' => '0435961469', 'email' => '', 'chi_nhanh' => "Đà Nẵng\nNha Trang",
) );
ob_start(); VHNB_Trang::ve( $U_NV ); $h_c = ob_get_clean();
t( 'bảng tin có tên công ty ở cuối trang',
	strpos( $h_c, 'CÔNG TY TNHH DỊCH VỤ VÀ GIẢI TRÍ K&amp;H' ) !== false, substr( $h_c, -600 ) );
t( 'có mã số thuế', strpos( $h_c, '0106924989' ) !== false );
/* 🔴 Nằm TRONG khung `.bo` của trang — xem chú thích cùng chốt ở `test-cham-cong.php`. */
t( '🔴 chân trang nằm trong khung .bo, không lọt ra ngoài',
	strpos( $h_c, '<div class="bo"><footer class="cty">' ) !== false,
	substr( $h_c, max( 0, strrpos( $h_c, 'footer class="cty"' ) - 120 ), 160 ) );
t( 'số điện thoại bấm gọi được', strpos( $h_c, 'href="tel:0435961469"' ) !== false );
t( 'ô email trống thì KHÔNG in nhãn Email:', strpos( $h_c, 'Email:' ) === false );
/* 🔴 Kể cả màn CHƯA ĐĂNG NHẬP — đó là thứ duy nhất người ngoài nhìn thấy. */
$_COOKIE = array();
ob_start(); VHNB_Trang::ve( null ); $h_cn = ob_get_clean();
t( 'màn chưa đăng nhập cũng có thông tin công ty',
	strpos( $h_cn, '0106924989' ) !== false, substr( $h_cn, -600 ) );
/* Chân trang là CHỮ — không được kéo theo script hay thuộc tính on*= nào (ngoài khối chat mini,
   thứ DUY NHẤT được phép có script — xem chú thích ở vhnb_bo_chat()). */
t( 'chân trang không mang script nào',
	stripos( vhnb_bo_chat( $h_c ), '<script' ) === false
		&& ! preg_match( '/\son[a-z]+\s*=\s*["\']/i', vhnb_bo_chat( $h_c ) ) );
teq( 'cả trang vẫn chỉ có MỘT khối kiểu chữ', 1, substr_count( $h_c, '<style>' ) );
t( 'và khối ấy có kiểu chữ của chân trang', strpos( $h_c, '.cty-ten{' ) !== false );

/* 🔴 MỘT CHỖ ĐÓNG TRANG, KHÔNG BA CHỖ. Canh thẳng vào mã, sau khi bỏ chú thích — chốt soi MÃ,
   không soi lời giải thích về mã. */
$ma_nb = '';
foreach ( token_get_all( file_get_contents( VHNB_DIR . 'includes/class-vhnb-trang.php' ) ) as $tk ) {
	if ( is_array( $tk ) && in_array( $tk[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) { continue; }
	$ma_nb .= is_array( $tk ) ? $tk[1] : $tk;
}
teq( 'class-vhnb-trang.php chỉ có ĐÚNG MỘT chỗ in </body></html>', 1,
	substr_count( $ma_nb, "'</body></html>'" ) );
teq( 'không còn dòng đóng trang gộp nào sót lại', 0, substr_count( $ma_nb, "</div></body></html>'" ) );

delete_option( 'vhg_chan' );

/* ================================================================= POST: chữ ký + chuyển hướng */

function vhnb_post( $tok, $post ) {
	$_COOKIE[ VHCC_Web::COOKIE ] = $tok;
	$_POST = $post;
	$GLOBALS['VHCP_CHUYEN'] = '';
	ob_start(); VHNB_Trang::phuc_vu(); $h = ob_get_clean();
	$_POST = array();
	return $h;
}
$KY_NV = VHNB_Trang::chu_ky( $TOK_NV );
$truoc = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHNB_DB::t( 'bai' ) );

/* 🔴 POST KHÔNG chữ ký -> KHÔNG được ghi gì. */
vhnb_post( $TOK_NV, array( 'viec' => 'dang', 'noi_dung' => 'bài giả mạo' ) );
teq( '🔴 POST thiếu chữ ký thì KHÔNG đăng được bài', $truoc,
	(int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHNB_DB::t( 'bai' ) ) );

/* Chữ ký của NGƯỜI KHÁC cũng không dùng được — nó buộc vào chính thẻ phiên. */
vhnb_post( $TOK_NV, array( 'viec' => 'dang', 'noi_dung' => 'mượn chữ ký', 'ky' => VHNB_Trang::chu_ky( $TOK_AD ) ) );
teq( '🔴 chữ ký của phiên khác cũng không dùng được', $truoc,
	(int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHNB_DB::t( 'bai' ) ) );
t( 'và người dùng được báo là biểu mẫu không hợp lệ',
	false !== strpos( json_encode( get_transient( 'vhnb_bao_' . md5( $TOK_NV ) ), JSON_UNESCAPED_UNICODE ), 'không hợp' ),
	get_transient( 'vhnb_bao_' . md5( $TOK_NV ) ) );

vhnb_post( $TOK_NV, array( 'viec' => 'dang', 'noi_dung' => 'bài thật', 'ky' => $KY_NV ) );
teq( 'POST đủ chữ ký thì đăng được', $truoc + 1,
	(int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHNB_DB::t( 'bai' ) ) );
/* 🔴 POST -> chuyển hướng -> GET, và bộ lọc nhóm phải theo sang. F5 mà đăng lại bài là chuyện
   người ta gặp thật, không phải giả định. */
t( 'đăng xong thì CHUYỂN HƯỚNG, không vẽ thẳng', '' !== $GLOBALS['VHCP_CHUYEN'], $GLOBALS['VHCP_CHUYEN'] );

vhnb_post( $TOK_NV, array( 'viec' => 'dang', 'noi_dung' => 'bài ở nhóm', 'ky' => $KY_NV,
	'nhom' => 'Văn phòng', 'nhom_xem' => 'Văn phòng' ) );
t( 'chuyển hướng giữ nguyên bộ lọc nhóm', false !== strpos( $GLOBALS['VHCP_CHUYEN'], 'nhom=' ),
	$GLOBALS['VHCP_CHUYEN'] );

/* Việc lạ thì báo không biết, chứ không im lặng coi như xong. */
vhnb_post( $TOK_NV, array( 'viec' => 'pha_hoai', 'ky' => $KY_NV ) );
t( 'việc lạ thì báo rõ, không im lặng',
	false !== strpos( json_encode( get_transient( 'vhnb_bao_' . md5( $TOK_NV ) ), JSON_UNESCAPED_UNICODE ), 'Không biết' ),
	get_transient( 'vhnb_bao_' . md5( $TOK_NV ) ) );

/* Chưa đăng nhập mà POST thì không ghi gì cả. */
$truoc2 = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHNB_DB::t( 'bai' ) );
vhnb_post( 'a0a0a0', array( 'viec' => 'dang', 'noi_dung' => 'người lạ', 'ky' => $KY_NV ) );
teq( 'chưa đăng nhập thì POST không ghi được gì', $truoc2,
	(int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHNB_DB::t( 'bai' ) ) );

/* ============================================================ nhóm riêng do người dùng tự lập */

/* 🔴 KHỐI NÀY CANH ĐÚNG MỘT CÂU HỎI: bài trong nhóm riêng có LỌT RA NGOÀI được không.
   Nhóm riêng là chỗ duy nhất trong cả hệ mà "ai đọc được" KHÔNG suy ra từ chức vụ — kể cả Admin
   ở ngoài cũng phải mù. Nên mọi đường đọc đều phải kiểm, không chỉ đường chính. */

vhnb_dung_bang();

/* Mời người vào nhóm là mời bằng MÃ, và mã phải có hồ sơ thật — nên dựng hồ sơ trước.
   ⚠️ Ghi thẳng vào bảng chứ không qua `luu_ho_so()`: bài này kiểm NHÓM, mượn đường ghi hồ sơ
   thì một ngày nào đó quyền ghi hồ sơ siết lại là khối nhóm đỏ oan. */
foreach ( array(
	array( 'NV001', 'Trần Văn A' ),
	array( 'NV002', 'Lê Thị B' ),
	array( 'NV009', 'Phạm Thị D' ),
) as $hs ) {
	$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => $hs[0], 'ho_ten' => $hs[1] ) );
}

$g1 = VHNB_Nhom::lap( $U_CHT, '  Nhóm xử lý sự cố  ', 'Bàn riêng khi máy hỏng' );
t( 'lập được nhóm', ! empty( $g1['ok'] ), $g1 );
$G1 = (int) $g1['id'];
$n1 = VHNB_Nhom::mot( $G1 );
teq( 'tên nhóm bị cắt khoảng trắng hai đầu', 'Nhóm xử lý sự cố', $n1['ten'] );
teq( 'người lập vào nhóm ngay, đếm được 1 thành viên', 1, (int) $n1['so_tv'] );
teq( 'người lập mang vai chủ nhóm', true, VHNB_Nhom::la_chu( $U_CHT, $n1 ) );

teq( 'nhóm không tên thì chối', false, VHNB_Nhom::lap( $U_CHT, "   \n " )['ok'] );

/* Tên dài quá thì CẮT, không chối — chối một cái tên dài là bắt người ta gõ lại từ đầu. */
$g_dai = VHNB_Nhom::lap( $U_CHT, str_repeat( 'x', VHNB_Nhom::TEN_TOI_DA + 60 ) );
teq( 'tên nhóm dài quá bị cắt đúng mức', VHNB_Nhom::TEN_TOI_DA,
	mb_strlen( VHNB_Nhom::mot( (int) $g_dai['id'] )['ten'], 'UTF-8' ) );
VHNB_Nhom::xoa( $U_CHT, (int) $g_dai['id'] );

/* ---- ai đọc được ---- */

teq( 'người lập vào được nhóm mình', true,  VHNB_Nhom::duoc_vao( $U_CHT, $G1 ) );
teq( 'người NGOÀI nhóm KHÔNG vào được', false, VHNB_Nhom::duoc_vao( $U_NV, $G1 ) );
/* 🔴 Admin cũng mù. Nếu ai cũng biết "sếp đọc được hết" thì không ai bàn gì trong đó nữa. */
teq( 'ADMIN ở ngoài nhóm CŨNG KHÔNG đọc được', false, VHNB_Nhom::duoc_vao( $U_AD, $G1 ) );
teq( 'nhóm không tồn tại thì không ai vào được', false, VHNB_Nhom::duoc_vao( $U_CHT, 999999 ) );

/* ---- mời người ---- */

teq( 'người ngoài KHÔNG mời được ai vào nhóm của người khác', false,
	VHNB_Nhom::moi( $U_NV, $G1, 'NV009' )['ok'] );
teq( 'Admin ở ngoài CŨNG không mời được', false, VHNB_Nhom::moi( $U_AD, $G1, 'NV009' )['ok'] );
teq( 'chưa nhập mã thì chối', false, VHNB_Nhom::moi( $U_CHT, $G1, '  ' )['ok'] );

$mo = VHNB_Nhom::moi( $U_CHT, $G1, 'NV001' );
t( 'chủ nhóm mời được người bằng MÃ NV', ! empty( $mo['ok'] ), $mo );
teq( 'mời xong thì người ấy vào được', true, VHNB_Nhom::duoc_vao( $U_NV, $G1 ) );
teq( 'đếm thành viên đi theo, không phải gõ tay', 2, (int) VHNB_Nhom::mot( $G1 )['so_tv'] );
teq( 'mời trùng một mã hai lần thì chối', false, VHNB_Nhom::moi( $U_CHT, $G1, 'NV001' )['ok'] );

/* ---- đăng bài trong nhóm ---- */

$b_nhom  = VHNB_Bai::dang( $U_CHT, 'Máy chấm công CS_VIVO hỏng, ai qua xem giúp.', '', $G1 );
t( 'thành viên đăng được bài vào nhóm', ! empty( $b_nhom['ok'] ), $b_nhom );
$B_NHOM  = (int) $b_nhom['id'];
$b_chung = VHNB_Bai::dang( $U_CHT, 'Thông báo chung ai cũng đọc.' );
$B_CHUNG = (int) $b_chung['id'];

/* 🔴 GÁC Ở LÕI, KHÔNG Ở MÀN. Màn chỉ liệt kê nhóm của mình, nhưng POST thì ai dựng ở đâu cũng
   gửi lên được — chốt phải nằm trong `dang()`, không nằm trong chỗ vẽ ô chọn. */
teq( 'người NGOÀI nhóm KHÔNG đăng bài vào nhóm được', false,
	VHNB_Bai::dang( $U_NV_LA, 'chen vào', '', $G1 )['ok'] );
teq( 'ADMIN ở ngoài CŨNG KHÔNG đăng bài vào nhóm được', false,
	VHNB_Bai::dang( $U_AD, 'sếp chen vào', '', $G1 )['ok'] );

/* ---- bài nhóm KHÔNG lọt ra bảng tin chung ---- */

$ids_chung = array();
foreach ( VHNB_Bai::bang_tin( '', 1 ) as $b ) { $ids_chung[] = (int) $b['id']; }
t( 'bảng tin CHUNG có bài chung', in_array( $B_CHUNG, $ids_chung, true ), $ids_chung );
t( '🔴 bảng tin CHUNG KHÔNG có bài của nhóm riêng',
	! in_array( $B_NHOM, $ids_chung, true ), $ids_chung );

/* Lọc theo BỘ PHẬN cũng là một đường đọc chung — bài nhóm không được lọt qua đó. */
foreach ( VHNB_Trang::ds_nhom() as $bp ) {
	$ids_bp = array();
	foreach ( VHNB_Bai::bang_tin( $bp, 1 ) as $b ) { $ids_bp[] = (int) $b['id']; }
	t( 'bài nhóm riêng không lọt qua bộ lọc bộ phận "' . $bp . '"',
		! in_array( $B_NHOM, $ids_bp, true ), $ids_bp );
}

$ids_g = array();
foreach ( VHNB_Bai::bang_tin( '', 1, $G1 ) as $b ) { $ids_g[] = (int) $b['id']; }
t( 'mở đúng nhóm thì thấy bài của nhóm', in_array( $B_NHOM, $ids_g, true ), $ids_g );
t( 'trong nhóm KHÔNG lẫn bài chung của cả công ty',
	! in_array( $B_CHUNG, $ids_g, true ), $ids_g );

/* ---- bình luận / thả tim / xoá cũng phải gác ---- */

teq( 'người ngoài KHÔNG bình luận được bài trong nhóm', false,
	VHNB_Bai::binh_luan( $U_NV_LA, $B_NHOM, 'nghe lén' )['ok'] );
teq( 'ADMIN ở ngoài CŨNG không bình luận được', false,
	VHNB_Bai::binh_luan( $U_AD, $B_NHOM, 'sếp nghe lén' )['ok'] );
teq( 'thành viên trong nhóm bình luận được', true,
	VHNB_Bai::binh_luan( $U_NV, $B_NHOM, 'em qua ngay' )['ok'] );
teq( 'người ngoài KHÔNG thả tim được bài trong nhóm', false,
	VHNB_Bai::tim( $U_NV_LA, $B_NHOM )['ok'] );
teq( 'thành viên thả tim được', true, VHNB_Bai::tim( $U_NV, $B_NHOM )['ok'] );
teq( 'doc_duoc(): người ngoài đọc KHÔNG được', false, VHNB_Bai::doc_duoc( $U_NV_LA, $B_NHOM ) );
teq( 'doc_duoc(): thành viên đọc được', true, VHNB_Bai::doc_duoc( $U_NV, $B_NHOM ) );

/* ---- rời / bỏ khỏi nhóm ---- */

/* 🔴 Chủ nhóm rời thì còn lại một đám không ai thêm bớt được ai — một nhóm chết vẫn hiện ra. */
teq( 'CHỦ NHÓM KHÔNG tự rời được', false, VHNB_Nhom::bo( $U_CHT, $G1, 'NV002' )['ok'] );
teq( 'người ngoài không bỏ được ai', false, VHNB_Nhom::bo( $U_NV_LA, $G1, 'NV001' )['ok'] );
teq( 'thành viên tự rời được', true, VHNB_Nhom::bo( $U_NV, $G1, 'NV001' )['ok'] );
teq( 'rời rồi thì hết đọc được', false, VHNB_Nhom::duoc_vao( $U_NV, $G1 ) );
teq( 'đếm thành viên giảm theo', 1, (int) VHNB_Nhom::mot( $G1 )['so_tv'] );
VHNB_Nhom::moi( $U_CHT, $G1, 'NV001' );
teq( 'chủ nhóm bỏ được người khác', true, VHNB_Nhom::bo( $U_CHT, $G1, 'NV001' )['ok'] );

/* ---- cua_toi() chỉ liệt kê nhóm mình ở trong ---- */

$g2 = VHNB_Nhom::lap( $U_NV, 'Nhóm của Trần Văn A' );
$G2 = (int) $g2['id'];
$ten_cua_cht = array();
foreach ( VHNB_Nhom::cua_toi( $U_CHT ) as $x ) { $ten_cua_cht[] = (int) $x['id']; }
t( 'cua_toi() có nhóm mình lập', in_array( $G1, $ten_cua_cht, true ), $ten_cua_cht );
t( '🔴 cua_toi() KHÔNG hé tên nhóm của người khác',
	! in_array( $G2, $ten_cua_cht, true ), $ten_cua_cht );

/* ---- xoá nhóm ---- */

teq( 'người ngoài không xoá được nhóm', false, VHNB_Nhom::xoa( $U_NV, $G1 )['ok'] );
/* Admin XOÁ được — quyền dọn dẹp không phải quyền đọc trộm. */
teq( 'ADMIN xoá được nhóm dù không đọc được', true, VHNB_Nhom::xoa( $U_AD, $G1 )['ok'] );
teq( 'xoá nhóm rồi thì nhóm không còn', null, VHNB_Nhom::mot( $G1 ) );
teq( '🔴 xoá nhóm thì bài trong đó đi theo, không để mồ côi', 0,
	(int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHNB_DB::t( 'bai' ) . ' WHERE nhom_id=' . $G1 ) );
teq( 'bình luận của bài trong nhóm cũng đi theo', 0,
	(int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHNB_DB::t( 'binh_luan' ) . ' WHERE bai_id=' . $B_NHOM ) );
teq( 'thả tim của bài trong nhóm cũng đi theo', 0,
	(int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHNB_DB::t( 'tim' ) . ' WHERE bai_id=' . $B_NHOM ) );
teq( 'bài CHUNG không bị xoá lây', 1,
	(int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHNB_DB::t( 'bai' ) . ' WHERE id=' . $B_CHUNG ) );

/* ---- trần số nhóm mỗi người ---- */

$goc_dem = (int) $wpdb->get_var( $wpdb->prepare(
	'SELECT COUNT(*) FROM ' . VHNB_DB::t( 'nhom' ) . ' WHERE ma_nv_tao=%s', 'NV002' ) );
for ( $i = $goc_dem; $i < VHNB_Nhom::TOI_DA_MOI_NGUOI; $i++ ) {
	VHNB_Nhom::lap( $U_CHT, 'nhóm ' . $i );
}
teq( 'quá trần thì chối, không lập vô hạn', false,
	VHNB_Nhom::lap( $U_CHT, 'nhóm thừa' )['ok'] );
teq( 'đúng bằng trần thì dừng, đếm từ số thật', VHNB_Nhom::TOI_DA_MOI_NGUOI,
	(int) $wpdb->get_var( $wpdb->prepare(
		'SELECT COUNT(*) FROM ' . VHNB_DB::t( 'nhom' ) . ' WHERE ma_nv_tao=%s', 'NV002' ) ) );

vhnb_dung_bang();


/* ============================================================ PHÂN QUYỀN TRANG NỘI BỘ */
/* Anh Thắng 26/08/2026: *"phần quyền người vào chỗ nào"* — và lúc đó không có chỗ nào.
   🔴 KHỐI NÀY CANH HAI THỨ: mặc định KHÔNG được siết chặt hơn hành vi đang chạy, và chốt phải
      nằm Ở LÕI chứ không ở màn hình. */

vhnb_dung_bang();
delete_option( VHNB_Quyen::O );

$U_QNV  = VHCC_Auth::user_by_token( VHCC_Auth::phat_token( 'Q Nhân Viên', 'Nhân viên',       'CS_VIVO', 'QNV1' ) );
$U_QCHT = VHCC_Auth::user_by_token( VHCC_Auth::phat_token( 'Q Cửa Hàng',  'Cửa hàng trưởng', 'CS_VIVO', 'QCH1' ) );
$U_QQL  = VHCC_Auth::user_by_token( VHCC_Auth::phat_token( 'Q Quản Lý',   'Quản lý',         '',        'QQL1' ) );
$U_QAD  = VHCC_Auth::user_by_token( VHCC_Auth::phat_token( 'Q Admin',     'Admin',           '',        'QAD1' ) );

/* ---- mặc định phải BẰNG ĐÚNG hành vi trước khi có màn này ---- */
/* 🔴 Bản nâng cấp mà đặt mặc định chặt hơn hiện tại là sáng hôm sau cả công ty mất quyền đăng
   bài, trong khi họ không đổi gì cả. */
foreach ( array( 'dang', 'nhom' ) as $_v ) {
	t( 'mặc định: Nhân viên VẪN ' . $_v . ' được', VHNB_Quyen::duoc( $U_QNV, $_v ), $_v );
}
t( 'mặc định: Nhân viên KHÔNG dọn bài người khác', ! VHNB_Quyen::duoc( $U_QNV, 'don' ) );
t( 'mặc định: Quản lý cũng KHÔNG dọn bài người khác', ! VHNB_Quyen::duoc( $U_QQL, 'don' ) );
t( 'mặc định: Admin dọn được', VHNB_Quyen::duoc( $U_QAD, 'don' ) );
t( 'việc không có tên thì CHỐI, không cho qua', ! VHNB_Quyen::duoc( $U_QAD, 'viec_la' ) );

/* ---- khai lại bậc ---- */
VHNB_Quyen::dat( array( 'dang' => 'CUA_HANG_TRUONG', 'don' => 'QUAN_LY' ) );
t( 'siết "đăng" lên Cửa hàng trưởng: Nhân viên hết đăng được', ! VHNB_Quyen::duoc( $U_QNV, 'dang' ) );
t( 'Cửa hàng trưởng vẫn đăng được', VHNB_Quyen::duoc( $U_QCHT, 'dang' ) );
t( 'bậc TRÊN luôn làm được việc của bậc dưới (Admin vẫn đăng được)',
	VHNB_Quyen::duoc( $U_QAD, 'dang' ) );
t( 'nới "dọn" xuống Quản lý: Quản lý dọn được', VHNB_Quyen::duoc( $U_QQL, 'don' ) );
t( 'nhưng Cửa hàng trưởng thì chưa', ! VHNB_Quyen::duoc( $U_QCHT, 'don' ) );
teq( 'việc không đụng tới thì giữ nguyên', 'NHAN_VIEN', VHNB_Quyen::cai_dat()['nhom'] );

/* Bậc lạ / việc lạ thì BỎ, không nhận bừa — một dòng gõ nhầm không được đổi luật. */
VHNB_Quyen::dat( array( 'dang' => 'SIEU_NHAN', 'viec_la' => 'ADMIN' ) );
teq( 'bậc lạ bị bỏ, giữ giá trị cũ', 'CUA_HANG_TRUONG', VHNB_Quyen::cai_dat()['dang'] );
t( 'việc lạ không lọt vào bảng', ! isset( VHNB_Quyen::cai_dat()['viec_la'] ) );

/* ---- CHỐT PHẢI NẰM Ở LÕI ---- */
/* 🔴 Màn hình có giấu ô soạn bài thì biểu mẫu POST vẫn dựng được từ bên ngoài. Giấu là trang
   trí; chặn ở lõi mới là chặn. */
t( '🔴 lõi CHỐI đăng bài khi chưa đủ bậc', empty( VHNB_Bai::dang( $U_QNV, 'chen vào' )['ok'] ) );
$_ok_b = VHNB_Bai::dang( $U_QCHT, 'bài hợp lệ' );
t( 'đủ bậc thì đăng được', ! empty( $_ok_b['ok'] ), $_ok_b );
t( '🔴 lõi CHỐI bình luận khi chưa đủ bậc',
	empty( VHNB_Bai::binh_luan( $U_QNV, (int) $_ok_b['id'], 'chen vào' )['ok'] ) );
t( '🔴 lõi CHỐI thả tim khi chưa đủ bậc',
	empty( VHNB_Bai::tim( $U_QNV, (int) $_ok_b['id'] )['ok'] ) );
VHNB_Quyen::dat( array( 'nhom' => 'QUAN_LY' ) );
t( '🔴 lõi CHỐI lập nhóm khi chưa đủ bậc', empty( VHNB_Nhom::lap( $U_QCHT, 'nhóm chen' )['ok'] ) );
t( 'đủ bậc thì lập nhóm được', ! empty( VHNB_Nhom::lap( $U_QQL, 'nhóm hợp lệ' )['ok'] ) );

/* Câu chối phải NÓI RA BẬC CẦN — "không đủ quyền" thì người đọc không biết phải xin ai. */
$_ly = VHNB_Quyen::vi_sao_khong( $U_QNV, 'dang' );
t( 'câu chối nói rõ cần bậc nào', false !== strpos( $_ly, 'Cửa hàng trưởng' ), $_ly );
teq( 'đủ quyền thì câu chối rỗng', '', VHNB_Quyen::vi_sao_khong( $U_QAD, 'dang' ) );

/* ---- KHÔNG CÒN CHỐT VAI Ở CỬA VÀO ----
   🔴 Anh Thắng 30/08/2026, khi một nhân viên đăng nhập xong bị chối *"Việc Vào trang Nội bộ cần
      vai từ Admin trở lên"*: *"trang nội bộ là chung công ty nên ai cũng vào được hết, có mật
      khẩu là vào, đó là lý do anh đặt trang chủ mà"*.

   Bản trước có ô chọn bậc cho việc VÀO. Chỉ cần chọn nhầm một lần là cả công ty mất trang chủ,
   mà người bị chối thì không hiểu vì sao — họ có PIN, họ đăng nhập được, rồi màn hình nói họ
   không đủ vai. Đã xảy ra thật trên máy anh.

   ⚠️ PHÉP THỬ NÀY PHẢI SỐNG SÓT QUA MỌI CÁCH KHAI. Kể cả khi bảng quyền đang siết chặt hết mức
      (dọn bài = Admin, đăng bài = Admin), một Nhân viên vẫn phải VÀO được. */
VHNB_Quyen::dat( array( 'dang' => 'ADMIN', 'nhom' => 'ADMIN', 'don' => 'ADMIN' ) );
ob_start(); VHNB_Trang::ve( $U_QNV ); $_h_nv = ob_get_clean();
t( '🔴 Nhân viên VÀO ĐƯỢC dù bảng quyền siết chặt hết mức',
	false !== strpos( $_h_nv, 'class="giua"' ), substr( $_h_nv, -2000 ) );
t( 'và KHÔNG còn màn "Chưa mở cho vai này"',
	false === strpos( $_h_nv, 'Chưa mở cho vai này' ), substr( $_h_nv, -2000 ) );
/* Siết vẫn phải có tác dụng với ĐĂNG BÀI — gỡ chốt vào không được kéo theo gỡ chốt đăng. */
t( 'nhưng vẫn KHÔNG đăng được (chốt đăng bài còn nguyên)',
	! VHNB_Quyen::duoc( $U_QNV, 'dang' ) );

/* 🔴 'vao' KHÔNG ĐƯỢC quay lại bảng phân quyền. `duoc()` chối mọi việc không có tên, nên chỗ
   nào lỡ hỏi `duoc( $u, 'vao' )` là chối sạch cả Admin — không còn ai vào để mở lại. */
t( '🔴 "vao" đã bị gỡ khỏi bảng phân quyền theo vai',
	! isset( VHNB_Quyen::VIEC['vao'] ) && ! isset( VHNB_Quyen::cai_dat()['vao'] ),
	VHNB_Quyen::cai_dat() );

delete_option( VHNB_Quyen::O );

/* ---- chốt THỨ HAI: khoá riêng TỪNG NGƯỜI, khai ở trang Quản lý nhân sự ----
   🔴 Hai chốt cùng đứng ở cửa và KHÔNG thay nhau: `VHNB_Quyen` khoá theo VAI, `VHCC_Cong` khoá
   theo NGƯỜI. Phép thử này phải là phép thử HÀNH VI (vẽ trang ra rồi soi), không phải một phép
   grep tìm chữ `VHCC_Cong::duoc_vao` trong mã: bọc lời gọi ấy trong `if ( false && … )` là mã
   vẫn còn nguyên chữ mà chốt thì không còn — grep xanh, cửa mở toang. */
t( 'đã nạp được sổ quyền vào trang của plugin chấm công', class_exists( 'VHCC_Cong' ) );
$_U_KHOA = VHCC_Auth::user_by_token( VHCC_Auth::phat_token( 'Người Bị Khoá', 'Admin', 'CS_VIVO', 'NBK1' ) );
ob_start(); VHNB_Trang::ve( $_U_KHOA ); $_h_truoc = ob_get_clean();
t( 'chưa khoá thì Admin vào nội bộ bình thường', false !== strpos( $_h_truoc, 'class="giua"' ) );
update_option( VHCC_Cong::O, array( 'NBK1' => array( 'noi_bo' => 'khoa' ) ) );
ob_start(); VHNB_Trang::ve( $_U_KHOA ); $_h_sau = ob_get_clean();
t( '🔴 khoá riêng ở trang Quản lý nhân sự thì KHÔNG vẽ bảng tin ra, kể cả với Admin',
	false === strpos( $_h_sau, 'class="giua"' ), $_h_sau );
t( 'và nói rõ là bị khoá riêng, không đổ cho vai',
	false !== strpos( $_h_sau, 'khoá riêng' ), $_h_sau );
/* Khoá một người KHÔNG được lây sang người khác. */
ob_start(); VHNB_Trang::ve( $U_QQL ); $_h_khac = ob_get_clean();
t( 'người khác không bị ảnh hưởng', false !== strpos( $_h_khac, 'class="giua"' ) );
delete_option( VHCC_Cong::O );

vhnb_dung_bang();

/* ============================================================ CHUÔNG THÔNG BÁO */
/* Anh Thắng: *"chỗ thông báo tin nhắn chỗ nào"* rồi *"Ví dụ như có chấm công, có chi phí nó
   sẽ hiện lên nội bộ này."* Nên hộp thư mở đúng MỘT CỬA NHẬN, ai có tin thì gọi vào. */

teq( 'chưa có tin thì đếm bằng 0', 0, VHNB_Bao::chua_doc( 'NV001' ) );
teq( 'và nhãn chuông để trống, không hiện số 0', '', VHNB_Bao::nhan_dem( 'NV001' ) );

t( 'nhận được tin từ nguồn ngoài (chấm công)',
	false !== VHNB_Bao::gui( 'NV001', 'cham_cong', 'Lê Thị B bù giờ công ngày 12/8', '', 'cc:12-8', 'NV002' ) );
teq( 'đếm lên 1', 1, VHNB_Bao::chua_doc( 'NV001' ) );
t( 'nhận được tin từ chi phí',
	false !== VHNB_Bao::gui( 'NV001', 'chi_phi', 'Đơn T8 đã được duyệt', '', 'cp:D1', '' ) );
teq( 'đếm lên 2', 2, VHNB_Bao::chua_doc( 'NV001' ) );

/* 🔴 KHÔNG TỰ BÁO CHO CHÍNH MÌNH — chuông kêu là chuông nói lại thứ người ta vừa làm. */
t( '🔴 tự gây ra thì KHÔNG báo cho chính mình',
	false === VHNB_Bao::gui( 'NV001', 'noi_bo', 'bạn bình luận bài của bạn', '', 'x:1', 'NV001' ) );
teq( 'nên số đếm không nhúc nhích', 2, VHNB_Bao::chua_doc( 'NV001' ) );
t( 'không có người nhận thì bỏ', false === VHNB_Bao::gui( '', 'noi_bo', 'gửi cho ai?', '', 'y:1' ) );
t( 'câu rỗng thì bỏ', false === VHNB_Bao::gui( 'NV001', 'noi_bo', '   ', '', 'y:2' ) );

/* ---- gộp theo khoá ---- */
/* Một bài 20 người bình luận mà đẻ 20 dòng thì chuông thành chỗ không ai mở. */
/* ⚠️ Mã người GÂY RA phải khác mã người NHẬN ở cả năm lượt — trùng một lượt là lượt ấy bị
   chốt "không tự báo cho mình" gạt đi, và con số 5 hụt xuống 4 vì lỗi của BÀI KIỂM. */
for ( $i = 0; $i < 5; $i++ ) {
	VHNB_Bao::gui( 'NV003', 'noi_bo', 'người thứ ' . $i . ' bình luận bài của bạn', '', 'bl:9', 'NGUOI' . $i );
}
teq( '🔴 năm lượt cùng khoá GỘP thành một dòng', 1, VHNB_Bao::chua_doc( 'NV003' ) );
$_ds3 = VHNB_Bao::ds( 'NV003' );
teq( 'và đếm đủ 5 lượt', 5, (int) $_ds3[0]['so_lan'] );
t( 'câu hiện ra là câu MỚI NHẤT', false !== strpos( (string) $_ds3[0]['chu'], 'thứ 4' ), $_ds3[0]['chu'] );

/* ---- đọc rồi thì đếm lại từ đầu ---- */
VHNB_Bao::danh_dau_doc( 'NV003' );
teq( 'đọc hết thì về 0', 0, VHNB_Bao::chua_doc( 'NV003' ) );
VHNB_Bao::gui( 'NV003', 'noi_bo', 'người mới bình luận bài của bạn', '', 'bl:9', 'NV009' );
teq( '🔴 đã đọc rồi mà có lượt mới thì SÁNG LẠI', 1, VHNB_Bao::chua_doc( 'NV003' ) );
$_ds3b = VHNB_Bao::ds( 'NV003' );
teq( 'và đếm LẠI TỪ 1, không cộng dồn xuyên qua lượt đọc', 1, (int) $_ds3b[0]['so_lan'] );

/* ---- đánh dấu đọc một tin, và không chạm hộp thư người khác ---- */
$_ds1 = VHNB_Bao::ds( 'NV001' );
VHNB_Bao::danh_dau_doc( 'NV001', (int) $_ds1[0]['id'] );
teq( 'đánh dấu một tin thì chỉ tin ấy đã đọc', 1, VHNB_Bao::chua_doc( 'NV001' ) );
/* ⚠️ Gửi lên id của người khác thì không được chạm tới. */
VHNB_Bao::danh_dau_doc( 'NV001', (int) $_ds3b[0]['id'] );
teq( '🔴 không đánh dấu được tin của người khác', 1, VHNB_Bao::chua_doc( 'NV003' ) );

/* ---- trần đếm ---- */
for ( $i = 0; $i < VHNB_Bao::DEM_TRAN + 5; $i++ ) {
	VHNB_Bao::gui( 'NV004', 'noi_bo', 'tin ' . $i, '', 'k' . $i, 'NVX' );
}
teq( 'quá trần thì hiện 99+, không hiện số dài', VHNB_Bao::DEM_TRAN . '+', VHNB_Bao::nhan_dem( 'NV004' ) );
t( 'một lượt đọc không bao giờ trả quá trần', count( VHNB_Bao::ds( 'NV004' ) ) <= VHNB_Bao::TOI_DA );

/* ---- chuông trên trang ---- */
$_U_C = VHCC_Auth::user_by_token( VHCC_Auth::phat_token( 'Người Có Tin', 'Nhân viên', 'CS_VIVO', 'NV001' ) );
ob_start(); VHNB_Trang::ve( $_U_C ); $_h_ch = ob_get_clean();
t( 'đầu trang có chuông', false !== strpos( $_h_ch, 'class="chuong"' ), 'không thấy .chuong' );
t( 'chuông hiện số tin chưa đọc', false !== strpos( $_h_ch, 'class="cham"' ) );
t( 'và bày ra câu của tin', false !== strpos( $_h_ch, 'Đơn T8 đã được duyệt' ) );
t( 'kèm nhãn nguồn để biết tin từ đâu', false !== strpos( $_h_ch, 'Chi phí' ) );
/* 🔴 Chuông KHÔNG được dùng script — cùng luật với màn quản trị chấm công. Chat mini (khối
   RIÊNG, xem vhnb_bo_chat()) là ngoại lệ DUY NHẤT của cả trang, không phải của chuông. */
t( '🔴 chuông không dùng một dòng script nào', ( function () use ( $_h_ch ) {
	$h = vhnb_bo_chat( $_h_ch );
	return false === stripos( $h, '<script' ) && ! preg_match( '/\son[a-z]+\s*=\s*["\']/i', $h );
} )(), 'có script' );
/* ⚠️ Mở trang KHÔNG được tự đánh dấu đã đọc: tải lại trang là con số về 0 dù chưa ai mở chuông. */
t( '🔴 mở trang KHÔNG tự đánh dấu đã đọc', VHNB_Bao::chua_doc( 'NV001' ) > 0 );

/* Người chưa có Mã NV thì hộp thư không có địa chỉ — nói thẳng, đừng treo chuông rỗng. */
$_U_KM = VHCC_Auth::user_by_token( VHCC_Auth::phat_token( 'Không Mã', 'Nhân viên', 'CS_VIVO', '' ) );
teq( 'không có mã thì đếm bằng 0, không nổ', 0, VHNB_Bao::chua_doc( '' ) );
teq( 'và đánh dấu đọc cũng không đụng vào gì', 0, VHNB_Bao::danh_dau_doc( '' ) );

vhnb_dung_bang();

/* ============================================================ ẢNH KÈM BÀI */
/* Anh Thắng 26/08/2026: *"bổ sung đăng được ảnh nhé em"*. */

vhnb_dung_bang();
$_U_A = VHCC_Auth::user_by_token( VHCC_Auth::phat_token( 'Người Đăng Ảnh', 'Admin', '', 'NVA1' ) );
$_up  = wp_upload_dir();
$_url_ok = $_up['baseurl'] . '/vhnb/2026/08/nb_test.jpg';

/* ---- chỉ nhận địa chỉ TRONG thư mục tải lên của chính web này ---- */
/* 🔴 Lưu được một địa chỉ bất kỳ là mở đường cho `javascript:` và cho ảnh nhúng từ web ngoài —
   ảnh ngoài thì mỗi lượt xem bảng tin là một lượt báo cho chủ web ấy biết ai vừa đọc gì. */
t( 'địa chỉ trong uploads/vhnb thì nhận', VHNB_Anh::hop_le( $_url_ok ), $_url_ok );
foreach ( array(
	'javascript:alert(1)',
	'https://web-khac.com/anh.jpg',
	$_up['baseurl'] . '/2026/08/anh-cua-nguoi-khac.jpg',
	'',
) as $_xau ) {
	t( 'chối địa chỉ lạ: ' . ( '' === $_xau ? '(rỗng)' : $_xau ), ! VHNB_Anh::hop_le( $_xau ) );
}

/* ---- đăng bài kèm ảnh ---- */
$_b_anh = VHNB_Bai::dang( $_U_A, 'có ảnh nhé', '', 0, $_url_ok );
t( 'đăng được bài kèm ảnh', ! empty( $_b_anh['ok'] ), $_b_anh );
$_r_anh = $wpdb->get_row( 'SELECT * FROM ' . VHNB_DB::t( 'bai' ) . ' WHERE id=' . (int) $_b_anh['id'], ARRAY_A );
teq( 'ảnh vào đúng cột', $_url_ok, (string) $_r_anh['anh'] );

/* 🔴 ĐỊA CHỈ LẠ THÌ BỎ ẢNH, VẪN ĐĂNG BÀI. Mất cái ảnh còn hơn mất cả bài, và người đăng thấy
   ngay là ảnh không lên. */
$_b_xau = VHNB_Bai::dang( $_U_A, 'ảnh lạ', '', 0, 'https://web-khac.com/x.jpg' );
t( 'ảnh lạ thì vẫn đăng được bài', ! empty( $_b_xau['ok'] ), $_b_xau );
teq( '🔴 nhưng KHÔNG lưu địa chỉ lạ ấy', '',
	(string) $wpdb->get_var( 'SELECT anh FROM ' . VHNB_DB::t( 'bai' ) . ' WHERE id=' . (int) $_b_xau['id'] ) );

/* 🔴 CÓ ẢNH THÌ KHÔNG CÒN LÀ BÀI RỖNG. Chốt "bài rỗng thì chối" viết từ hồi chưa có ảnh; giữ
   nguyên là đăng ảnh không kèm chữ bị chối — mà đó là cách người ta đăng ảnh nhiều nhất. */
$_b_chi_anh = VHNB_Bai::dang( $_U_A, '   ', '', 0, $_url_ok );
t( '🔴 đăng ảnh KHÔNG kèm chữ vẫn được', ! empty( $_b_chi_anh['ok'] ), $_b_chi_anh );
t( 'nhưng rỗng cả chữ lẫn ảnh thì vẫn chối',
	empty( VHNB_Bai::dang( $_U_A, '   ', '', 0, '' )['ok'] ) );

/* ---- trên màn ---- */
ob_start(); VHNB_Trang::ve( $_U_A ); $_h_anh = ob_get_clean();
t( 'ô soạn bài có nút đính ảnh', false !== strpos( $_h_anh, 'name="anh"' ), 'không thấy ô chọn tệp' );
/* ⚠️ Thiếu `enctype` thì trình duyệt vẫn gửi biểu mẫu, vẫn không báo lỗi gì — chỉ là `$_FILES`
   rỗng và ảnh im lặng không bao giờ lên. */
t( '🔴 biểu mẫu đăng bài có enctype multipart',
	false !== strpos( $_h_anh, 'enctype="multipart/form-data"' ), 'thiếu enctype' );
t( 'bài có ảnh thì vẽ thẻ img', false !== strpos( $_h_anh, 'class="bai-anh"' ), 'không thấy ảnh' );
t( 'và ảnh tải chậm (lazy) — bảng tin 20 bài không kéo 20 ảnh một lượt',
	false !== strpos( $_h_anh, 'loading="lazy"' ) );
t( 'ảnh có kiểu chữ thật', false !== strpos( $_h_anh, '.bai-anh img{' ) );
/* Vẫn không một dòng script LẠ nào — người ở cơ sở mở bằng điện thoại cũ trên 3G. Chat mini (xem
   vhnb_bo_chat()) là script DUY NHẤT được phép có trên cả trang, cắt nó ra rồi mới soi phần còn
   lại — bài đăng có ảnh không được kéo theo bất kỳ script nào của riêng nó. */
t( 'thêm ảnh mà trang vẫn KHÔNG dùng script lạ', ( function () use ( $_h_anh ) {
	$h = vhnb_bo_chat( $_h_anh );
	return false === stripos( $h, '<script' ) && ! preg_match( '/\son[a-z]+\s*=\s*["\']/i', $h );
} )() );

/* ================================================================= chat mini (tin nhắn riêng)
   Anh Thắng 30/08/2026: *"bổ sung tab chat mini bên dưới để chat với thành viên"*, chốt kiểu tự
   cập nhật. NV001/NV002/NV009 vẫn còn hồ sơ thật từ khối "nhóm riêng" ở trên (chưa
   `vhnb_dung_bang()` lại `nhan_vien` — bảng đó thuộc plugin chấm công, không nằm trong
   `VHNB_DB::bang()`) nên dùng lại được luôn, không phải dựng lại. */

$r = VHNB_Tin::gui( array(), 'NV001', 'xin chào' );
teq( 'chưa đăng nhập thì không gửi được', false, $r['ok'] );

$r = VHNB_Tin::gui( array( 'name' => 'Không Mã', 'ma_nv' => '' ), 'NV001', 'xin chào' );
t( 'chưa có Mã NV thì không gửi được', empty( $r['ok'] ), $r );
t( 'và nói rõ vì sao', false !== strpos( $r['error'], 'Mã NV' ), $r['error'] );

$r = VHNB_Tin::gui( $U_NV, '', 'xin chào' );
t( 'thiếu người nhận thì chối', empty( $r['ok'] ), $r );

$r = VHNB_Tin::gui( $U_NV, 'NV001', 'xin chào' );
t( '🔴 tự nhắn cho chính mình thì chối', empty( $r['ok'] ), $r );

$r = VHNB_Tin::gui( $U_NV, 'MA_KHONG_CO', 'xin chào' );
t( 'nhắn cho mã không có hồ sơ thì chối', empty( $r['ok'] ), $r );
t( 'và nói rõ gõ đúng mã, không phải tên', false !== strpos( $r['error'], 'Gõ đúng mã' ), $r['error'] );

$r = VHNB_Tin::gui( $U_NV, 'NV002', "   \n\n  " );
t( 'tin rỗng (toàn khoảng trắng) bị chối', empty( $r['ok'] ), $r );

$r1 = VHNB_Tin::gui( $U_NV, 'NV002', 'Chào chị B, mai có ca không?' );
t( 'gửi được tin cho người có hồ sơ thật', ! empty( $r1['ok'] ), $r1 );
teq( 'trả về đúng tên người nhận (từ hồ sơ)', 'Lê Thị B', $r1['denTen'] );
$M1 = (int) $r1['id'];
t( 'trả về id thật', $M1 > 0, $M1 );

$hang = $wpdb->get_row( 'SELECT * FROM ' . VHNB_DB::t( 'tin_nhan' ) . ' WHERE id=' . $M1, ARRAY_A );
teq( 'người gửi lấy TỪ PHIÊN', 'NV001', $hang['tu'] );
teq( 'tên người gửi lấy TỪ PHIÊN', 'Trần Văn A', $hang['tu_ten'] );
teq( 'người nhận đúng mã đã gõ', 'NV002', $hang['den'] );
teq( 'chưa đọc lúc mới gửi', 0, (int) $hang['da_doc'] );

/* 🔴 KHÔNG cho gửi thay người khác — canh bằng chữ ký hàm, vì đó là thứ chặn thật. */
$rf_tin = new ReflectionMethod( 'VHNB_Tin', 'gui' );
$ten_ts = array();
foreach ( $rf_tin->getParameters() as $p ) { $ten_ts[] = $p->getName(); }
teq( 'gui() không có tham số nào nhận tên/mã người gửi tự do', array( 'u', 'den_ma', 'noi_dung' ), $ten_ts );

/* ---- đọc một cuộc trò chuyện ---- */
$r2 = VHNB_Tin::gui( $U_CHT, 'NV001', 'Ừ mai có ca sáng.' );
t( 'người nhận trả lời được', ! empty( $r2['ok'] ), $r2 );

$ct = VHNB_Tin::tin_gan_day( 'NV001', 'NV002' );
teq( 'đọc đủ hai chiều, đúng thứ tự cũ trước mới sau', 2, count( $ct ) );
teq( 'tin đầu là tin NV001 gửi trước', 'Chào chị B, mai có ca không?', $ct[0]['noi_dung'] );
teq( 'tin sau là NV002 trả lời', 'Ừ mai có ca sáng.', $ct[1]['noi_dung'] );
teq( '🔴 đọc CHIỀU NGƯỢC LẠI (từ NV002) ra cùng nội dung, cùng thứ tự',
	$ct, VHNB_Tin::tin_gan_day( 'NV002', 'NV001' ) );

t( '🔴 người NGOÀI cuộc trò chuyện không đọc được gì (mã sai)',
	empty( VHNB_Tin::tin_gan_day( 'NV009', 'NV001' ) ) );

/* ---- tin MỚI kể từ một mốc id (dùng cho polling) ---- */
$id_dau = (int) $ct[0]['id'];
$tin_moi_ds = VHNB_Tin::tin_moi( 'NV001', 'NV002', $id_dau );
teq( 'chỉ lấy tin SAU mốc, không lấy lại tin đã có', 1, count( $tin_moi_ds ) );
teq( 'đúng là tin thứ hai', 'Ừ mai có ca sáng.', $tin_moi_ds[0]['noi_dung'] );
t( 'mốc = tin mới nhất thì không còn gì mới',
	empty( VHNB_Tin::tin_moi( 'NV001', 'NV002', (int) $ct[1]['id'] ) ) );

/* ---- đánh dấu đã đọc ---- */
teq( 'NV001 có đúng 1 tin chưa đọc (tin NV002 vừa gửi)', 1, VHNB_Tin::dem_chua_doc( 'NV001' ) );
teq( 'NV002 có đúng 1 tin chưa đọc (tin NV001 gửi trước đó)', 1, VHNB_Tin::dem_chua_doc( 'NV002' ) );
VHNB_Tin::danh_dau_doc( 'NV001', 'NV002' );
teq( 'đánh dấu xong thì NV001 hết tin chưa đọc', 0, VHNB_Tin::dem_chua_doc( 'NV001' ) );
teq( '🔴 nhưng KHÔNG đụng tới tin chưa đọc của NV002', 1, VHNB_Tin::dem_chua_doc( 'NV002' ) );

/* ---- danh sách cuộc trò chuyện ---- */
VHNB_Tin::gui( $U_NV_LA, 'NV001', 'Anh ơi cho em hỏi lịch nghỉ lễ' );
$ds_ct = VHNB_Tin::ds_cuoc_tro_chuyen( 'NV001' );
teq( 'NV001 có 2 cuộc trò chuyện (NV002 và NV009)', 2, count( $ds_ct ) );
teq( '🔴 cuộc MỚI NHẤT đứng đầu', 'NV009', $ds_ct[0]['ma'] );
teq( 'kèm đúng tin cuối', 'Anh ơi cho em hỏi lịch nghỉ lễ', $ds_ct[0]['tinCuoi'] );
teq( 'đánh dấu ĐÚNG là tin của người kia, không phải của tôi', false, $ds_ct[0]['tinCuoiToi'] );
teq( 'cuộc với NV002 tụt xuống dưới', 'NV002', $ds_ct[1]['ma'] );
teq( 'và đã đọc hết (0 chưa đọc) sau khi đánh dấu ở trên', 0, $ds_ct[1]['chuaDoc'] );
teq( 'cuộc với NV009 còn 1 tin chưa đọc', 1, $ds_ct[0]['chuaDoc'] );

t( 'người chưa từng nhắn với ai thì danh sách rỗng', empty( VHNB_Tin::ds_cuoc_tro_chuyen( 'CHUA_NHAN_AI' ) ) );

/* ---- VHNB_Trang::xu_ly_ajax_tin(): việc thuần, không cần dựng cả lượt HTTP ---- */
$kq = VHNB_Trang::xu_ly_ajax_tin( $U_NV, 'dem', array() );
teq( 'viec "dem" trả đúng số tin chưa đọc (1, từ NV009 vừa gửi ở trên)', 1, $kq['demChuaDoc'] );

$kq = VHNB_Trang::xu_ly_ajax_tin( $U_NV, 'ds', array() );
t( 'viec "ds" trả về danh sách cuộc trò chuyện', ! empty( $kq['ok'] ) && 2 === count( $kq['cuoc'] ), $kq );

$kq = VHNB_Trang::xu_ly_ajax_tin( $U_NV, 'lay', array( 'voi' => 'NV002' ) );
t( 'viec "lay" trả về tin của đúng cuộc trò chuyện', ! empty( $kq['ok'] ) && 2 === count( $kq['tin'] ), $kq );

$kq = VHNB_Trang::xu_ly_ajax_tin( $U_NV, 'lay', array() );
t( 'viec "lay" thiếu "voi" thì chối', empty( $kq['ok'] ), $kq );

$kq = VHNB_Trang::xu_ly_ajax_tin( $U_NV, 'gui', array( 'voi' => 'NV002', 'nd' => 'test qua ajax' ) );
t( 'viec "gui" gọi đúng đường VHNB_Tin::gui()', ! empty( $kq['ok'] ), $kq );

$kq = VHNB_Trang::xu_ly_ajax_tin( $U_NV, 'lung_tung', array() );
t( 'việc lạ thì chối, không âm thầm bỏ qua', empty( $kq['ok'] ), $kq );

/* ---- hiện/ẩn tab chat trên trang, theo Mã NV + quyền ---- */
$_COOKIE[ VHCC_Web::COOKIE ] = $TOK_NV;
ob_start(); VHNB_Trang::ve( $U_NV ); $h_chat = ob_get_clean();
t( '🔴 người có Mã NV thấy tab chat mini', strpos( $h_chat, 'id="vhnb-chat-tab"' ) !== false );
/* ⚠️ Soi `admin-ajax.php` KHÔNG KÈM DẤU `/` XUNG QUANH: `wp_json_encode()` mặc định thoát dấu
   `/` thành `\/` trong chuỗi JSON, nên URL thật nằm trong `data-cfg` dưới dạng đã thoát — trình
   duyệt `JSON.parse()` tự hiểu đúng lúc chạy, nhưng soi chuỗi thô ở đây mà kèm `/` thì trật. */
t( 'mang đúng đường admin-ajax.php', strpos( $h_chat, 'admin-ajax.php' ) !== false );
t( 'nút gửi bằng form vẫn mang chữ ký (tắt JS thì vẫn chối được form giả)',
	1 === substr_count( $h_chat, 'id="vhnb-chat-form"' )
	&& false !== strpos( substr( $h_chat, strpos( $h_chat, 'id="vhnb-chat-form"' ), 300 ), 'name="ky"' ), $h_chat );
t( '🔴 vhnb_bo_chat() thật sự cắt được khối chat ra — nếu không, mọi phép thử "không script" '
	. 'phía trên coi như không canh gì cả', strlen( vhnb_bo_chat( $h_chat ) ) < strlen( $h_chat ),
	array( strlen( $h_chat ), strlen( vhnb_bo_chat( $h_chat ) ) ) );

$U_KM = array( 'name' => 'Chưa Có Mã', 'role' => 'Nhân viên', 'ma_nv' => '' );
ob_start(); VHNB_Trang::ve( $U_KM ); $h_km = ob_get_clean();
t( '🔴 người CHƯA có Mã NV thì KHÔNG thấy tab chat — VHNB_Tin::gui() sẽ chối mọi lượt gửi',
	strpos( $h_km, 'id="vhnb-chat-tab"' ) === false, substr( $h_km, -3000 ) );

$_COOKIE = array();
ob_start(); VHNB_Trang::ve( null ); $h_null = ob_get_clean();
t( 'chưa đăng nhập thì cũng không thấy tab chat', strpos( $h_null, 'id="vhnb-chat-tab"' ) === false );

vhnb_dung_bang();

/* ================================================================= đường dẫn của trang */

teq( 'đường dẫn mặc định là noi-bo', 'noi-bo', VHNB_Trang::slug() );
update_option( 'vhnb_slug', 'noi-bo-cong-ty' );
teq( 'đổi được đường dẫn', 'noi-bo-cong-ty', VHNB_Trang::slug() );
update_option( 'vhnb_slug', '' );
teq( 'để trống thì về mặc định, KHÔNG để rỗng (rỗng là trang không có địa chỉ)',
	'noi-bo', VHNB_Trang::slug() );

/* ==================================================================================
   ĐĂNG NHẬP NGAY TẠI TRANG NÀY, VÀ THOÁT VỀ LẠI TRANG NÀY

   Anh Thắng 30/08/2026: *"Đăng nhập 1 trang nội bộ là dùng được tất cả các trang"*, rồi nói rõ
   thêm: *"khi thoát thì nó trở về trang nội bộ, có đăng nhập thì lại từ đầu, còn đã đăng nhập
   thì dùng đâu cũng được"*.

   🔴 CHỐT ĐẮT NHẤT CỦA CẢ KHỐI: ô PIN ở đây phải đi qua ĐÚNG `VHCC_Auth::login()`. Viết một cửa
      PIN thứ hai thì có hai bộ đếm nhập sai rời nhau — kẻ dò PIN bị khoá ở cửa này cứ sang cửa
      kia dò tiếp, và nhìn vào mã cửa nào cũng thấy "có khoá". Phép thử dưới chứng minh điều đó
      bằng cách gõ sai 10 lượt Ở ĐÂY rồi xem cửa BÊN KIA có khoá theo không.
   ================================================================================== */

vhnb_dung_bang();
$_GET = array(); $_POST = array(); $_COOKIE = array();
$GLOBALS['VHCP_CHUYEN'] = '';

$nguon_cu = get_option( 'vhcc_nguon_nguoidung' );
update_option( 'vhcc_nguon_nguoidung', 'chung' );
$bang_cfg_dn = $wpdb->prefix . 'vhcp_cfg';
$wpdb->exec_raw( "DELETE FROM $bang_cfg_dn WHERE bang='CH_NguoiDung'" );
foreach ( array(
	array( 'Chị Vào Được', '778899', 'Quản lý',   'CS_VIVO' ),
	array( 'Em Nhân Viên', '112233', 'Nhân viên', 'CS_VIVO' ),
) as $i_dn => $x_dn ) {
	$wpdb->insert( $bang_cfg_dn, array( 'bang' => 'CH_NguoiDung', 'stt' => $i_dn + 1,
		'cols' => wp_json_encode( $x_dn ) ) );
}
VHCC_Auth::mo_khoa();

VHCC_Web::$cookie_da_dat = array();
$r_dn = VHNB_Trang::dang_nhap( '778899' );
t( 'PIN đúng -> mở được phiên ngay tại trang Nội bộ', ! empty( $r_dn['ok'] ), $r_dn );
teq( 'và không kèm câu báo lỗi nào', '', (string) $r_dn['loi'] );
/* 🔴 BÁO "VÀO ĐƯỢC" MÀ QUÊN ĐẶT COOKIE là lỗi không ai lần ra: người dùng gõ đúng PIN, trang
   nháy một cái rồi hiện lại y như cũ, không câu báo nào. `setcookie()` thì bài kiểm không soi
   được (chạy dòng lệnh nó im lặng, `$_COOKIE` không đổi), nên soi sổ ghi của `VHCC_Web`. */
teq( '🔴 và ĐẶT COOKIE phiên thật, không chỉ báo suông', array( 'dat' ), VHCC_Web::$cookie_da_dat );

VHCC_Auth::mo_khoa();
$r_sai = VHNB_Trang::dang_nhap( '000000' );
t( 'PIN sai -> chối', empty( $r_sai['ok'] ), $r_sai );
t( 'và nói ra lý do', '' !== (string) $r_sai['loi'], $r_sai );
/* 🔴 KHÔNG BAO GIỜ ĐƯA PIN VÀO CÂU BÁO — câu này hiện lên một trang ai cũng mở được. */
t( '🔴 câu báo KHÔNG chứa PIN vừa gõ', false === strpos( (string) $r_sai['loi'], '000000' ), $r_sai );

/* 🔴 GÁC BẰNG ĐÚNG DANH SÁCH MÀ `user_by_token` DÙNG (`vai_tro_vao`). Lệch một cái là người ta
   gõ PIN đúng, cookie được đặt, rồi lượt sau `toi()` trả null — màn hình quay lại y như cũ,
   không một câu giải thích, và họ gõ lại mười lần cho tới lúc tự khoá mình. */
VHCC_Auth::mo_khoa();
$wpdb->insert( $bang_cfg_dn, array( 'bang' => 'CH_NguoiDung', 'stt' => 9,
	'cols' => wp_json_encode( array( 'Bác Vai Lạ', '667788', 'Khách vãng lai', 'CS_VIVO' ) ) ) );
VHCC_Web::$cookie_da_dat = array();
$r_vai = VHNB_Trang::dang_nhap( '667788' );
t( '🔴 PIN đúng nhưng vai KHÔNG vào được -> chối, và nói rõ là vì vai chứ không phải PIN sai',
	empty( $r_vai['ok'] ) && false !== strpos( (string) $r_vai['loi'], 'Bác Vai Lạ' ), $r_vai );
teq( 'và KHÔNG đặt cookie cho người bị chối', array(), VHCC_Web::$cookie_da_dat );

VHCC_Auth::mo_khoa();
$r_khuon = VHNB_Trang::dang_nhap( 'abc' );
t( 'PIN sai khuôn -> chối bằng đúng câu của cửa cũ',
	empty( $r_khuon['ok'] ) && false !== strpos( (string) $r_khuon['loi'], '4–8' ), $r_khuon );

/* 🔴 MỘT BỘ CHỐT, KHÔNG PHẢI HAI. Gõ sai 10 lượt Ở TRANG NỘI BỘ thì cửa của hệ chấm công cũng
   phải khoá theo — vì đó vốn là CÙNG một bộ đếm. Tự viết cửa riêng ở đây là phép thử này đỏ. */
VHCC_Auth::mo_khoa();
for ( $i_dn = 0; $i_dn < 10; $i_dn++ ) { VHNB_Trang::dang_nhap( '999999' ); }
$kq_ben_kia = VHCC_Auth::login( '778899' );
t( '🔴 dò sai 10 lượt ở trang Nội bộ thì cửa BÊN CHẤM CÔNG khoá theo (một bộ chốt, không phải hai)',
	empty( $kq_ben_kia['ok'] ) && false !== strpos( (string) $kq_ben_kia['error'], '10 phút' ),
	$kq_ben_kia );
VHCC_Auth::mo_khoa();

/* Lượt POST thật: chưa đăng nhập mà gửi `viec=dang_nhap` thì phải vào được, rồi chuyển hướng
   VỀ CHÍNH TRANG NÀY — anh Thắng: *"có đăng nhập thì lại từ đầu"*. */
$_POST = array( 'viec' => 'dang_nhap', 'pin' => '778899' );
$GLOBALS['VHCP_CHUYEN'] = '';
ob_start(); VHNB_Trang::phuc_vu(); $h_dn = ob_get_clean();
teq( 'POST đăng nhập -> chuyển hướng về chính trang Nội bộ',
	VHNB_Trang::url(), (string) $GLOBALS['VHCP_CHUYEN'] );
teq( 'và không vẽ gì thêm ra', '', $h_dn );

/* 🔴 CHỐT CHỮ KÝ KHÔNG ĐƯỢC CHẶN LƯỢT ĐĂNG NHẬP. `ky_dung()` tính chữ ký theo THẺ PHIÊN, mà
   lượt này chưa có thẻ nào — đặt nhầm thứ tự thì ô PIN nằm đó nhìn thấy được nhưng không bao
   giờ vào nổi, và không một câu báo nào giải thích. */
t( '🔴 lượt đăng nhập KHÔNG bị chốt chữ ký chặn (không có thẻ thì lấy đâu ra chữ ký)',
	'' === $h_dn && '' !== (string) $GLOBALS['VHCP_CHUYEN'] );

/* PIN sai qua lượt POST: vẽ THẲNG lại trang kèm câu báo, KHÔNG chuyển hướng — vì câu báo cất
   trong transient thì khoá tên theo thẻ phiên, mà chưa đăng nhập nghĩa là thẻ rỗng, tức mọi
   khách trên internet dùng CHUNG một ô nhớ. */
VHCC_Auth::mo_khoa();
$_POST = array( 'viec' => 'dang_nhap', 'pin' => '000001' );
$GLOBALS['VHCP_CHUYEN'] = '';
ob_start(); VHNB_Trang::phuc_vu(); $h_sai = ob_get_clean();
teq( 'PIN sai -> KHÔNG chuyển hướng', '', (string) $GLOBALS['VHCP_CHUYEN'] );
t( 'mà vẽ lại trang kèm câu báo đỏ',
	false !== strpos( $h_sai, 'bao loi' ) && false !== strpos( $h_sai, 'name="pin"' ),
	substr( $h_sai, -1600 ) );
t( '🔴 và KHÔNG trả lại PIN vừa gõ vào ô nhập', false === strpos( $h_sai, '000001' ),
	substr( $h_sai, -1600 ) );
$_POST = array();
VHCC_Auth::mo_khoa();

/* -------------------------------------------------------------------- THOÁT */

$TOK_RA = VHCC_Auth::phat_token( 'Chị Vào Được', 'Quản lý', 'CS_VIVO', 'NV009' );
$_COOKIE[ constant( 'VHCC_Web::COOKIE' ) ] = $TOK_RA;
t( 'trước khi thoát: thẻ còn sống', null !== VHCC_Auth::user_by_token( $TOK_RA ) );

$U_RA = VHNB_Trang::toi();
t( 'và trang nhận ra người đang đăng nhập', is_array( $U_RA ), $U_RA );

ob_start(); VHNB_Trang::ve( $U_RA ); $h_co = ob_get_clean();
t( 'thanh đầu có nút Thoát', false !== strpos( $h_co, 'name="viec" value="thoat"' ), substr( $h_co, -2500 ) );
/* Nút Thoát là FORM POST kèm chữ ký, không phải một đường dẫn: một cái link đăng xuất thì chỉ
   cần dán địa chỉ ấy vào bảng tin là cả phòng bị đá ra. */
t( 'nút Thoát là biểu mẫu POST có chữ ký, không phải đường dẫn',
	preg_match( '#<form method="post"[^>]*>\s*<input type="hidden" name="ky"[^>]*>\s*'
		. '<input type="hidden" name="viec" value="thoat">#', $h_co ) === 1, substr( $h_co, 0, 4000 ) );

$_POST = array( 'viec' => 'thoat', 'ky' => VHNB_Trang::chu_ky( $TOK_RA ) );
$GLOBALS['VHCP_CHUYEN'] = '';
VHCC_Web::$cookie_da_dat = array();
ob_start(); VHNB_Trang::phuc_vu(); $h_ra_x = ob_get_clean();
teq( 'thoát -> quay về TRANG NỘI BỘ, không nhảy sang trang chấm công',
	VHNB_Trang::url(), (string) $GLOBALS['VHCP_CHUYEN'] );
/* 🔴 THOÁT LÀ THOÁT THẬT. Ba trang dùng chung MỘT thẻ, và trạm chấm công còn giữ chính thẻ ấy
   trong localStorage. Xoá mỗi cookie thì người vừa bấm Thoát trên máy chung tưởng mình đã ra,
   trong khi thẻ vẫn sống 12 tiếng ở chỗ khác. */
teq( '🔴 thẻ bị HUỶ hẳn, không chỉ xoá cookie của trình duyệt này',
	null, VHCC_Auth::user_by_token( $TOK_RA ) );
/* Và cookie cũng phải được xoá — không thì trình duyệt còn mang một chuỗi rác suốt 12 tiếng. */
t( '🔴 và cookie phiên bị XOÁ', in_array( 'xoa', VHCC_Web::$cookie_da_dat, true ),
	VHCC_Web::$cookie_da_dat );
$_POST = array();

/* Không có chữ ký thì không thoát được — nếu không, dán một biểu mẫu ở trang ngoài là đá được
   người khác ra khỏi hệ. */
$TOK_R2 = VHCC_Auth::phat_token( 'Chị Vào Được', 'Quản lý', 'CS_VIVO', 'NV009' );
$_COOKIE[ constant( 'VHCC_Web::COOKIE' ) ] = $TOK_R2;
$_POST = array( 'viec' => 'thoat', 'ky' => 'chu-ky-gia' );
$GLOBALS['VHCP_CHUYEN'] = '';
ob_start(); VHNB_Trang::phuc_vu(); ob_get_clean();
t( '🔴 chữ ký sai thì KHÔNG thoát được ai cả',
	null !== VHCC_Auth::user_by_token( $TOK_R2 ), $GLOBALS['VHCP_CHUYEN'] );
$_POST = array();

/* `thoat()` khi chưa đăng nhập: không có gì để huỷ, và KHÔNG được ném lỗi. */
$_COOKIE = array();
teq( 'chưa đăng nhập mà bấm thoát -> false, không ném lỗi', false, VHNB_Trang::thoat() );

/* -------------------------------------------- thoát ở TRANG CHẤM CÔNG thì về đâu

   Anh Thắng: *"khi thoát thì nó trở về trang nội bộ"*. Khi Nội bộ đang là TRANG CHỦ thì đó mới
   là cửa trước của cả hệ — thoát ra mà đứng ở màn PIN riêng của trang chấm công là người ta
   mất luôn đường về nhà. */
$tc_cu = get_option( 'vhnb_trang_chu' );
update_option( 'vhnb_trang_chu', 0 );
teq( 'chưa bật làm trang chủ -> thoát ở chấm công vẫn về trang chấm công',
	VHCC_Web::url(), VHCC_Web::noi_ve_sau_thoat() );
update_option( 'vhnb_trang_chu', 1 );
teq( '🔴 bật làm trang chủ -> thoát ở chấm công về TRANG NỘI BỘ',
	VHNB_Trang::url(), VHCC_Web::noi_ve_sau_thoat() );
update_option( 'vhnb_trang_chu', $tc_cu ? 1 : 0 );

/* Dọn lại nguồn người dùng — bẫy thứ tự khối thử đã cắn nhiều lần trong dự án này. */
$wpdb->exec_raw( "DELETE FROM $bang_cfg_dn WHERE bang='CH_NguoiDung'" );
update_option( 'vhcc_nguon_nguoidung', $nguon_cu ? $nguon_cu : 'ho_so' );
$_GET = array(); $_POST = array(); $_COOKIE = array();
vhnb_dung_bang();

/* ==================================================================================
   CÁC TRANG KHÁC — một chỗ khai duy nhất

   Anh Thắng 30/08/2026, nhìn khối "Các trang khác" chỉ có Chấm công và Vận hành chi phí:
   *"em quên trang ghế à"*, rồi chốt: *"sau trang mới thì dùng chung hết, thiết lập sẵn luôn"*.

   🔴 GÕ TAY TỪNG CÁI NÚT LÀ SAI TỪ GỐC. Mỗi lần dựng một trang mới lại phải nhớ đi sửa từng
      chỗ liệt kê, và chỗ nào quên thì trang mới coi như không tồn tại với người dùng. Nên đọc
      thẳng bảng trang của plugin Cổng K&H — thêm trang mới là thêm MỘT mục ở đó.
   ================================================================================== */

/* ---- nhánh DỰ PHÒNG: chưa cài plugin Cổng thì vẫn phải có đường đi ----
   Dựng lớp giả cho plugin Ghế: nạp cả plugin ghế vào bài này thì nặng, mà thứ cần dựng lại chỉ
   là hoàn cảnh "có plugin ấy, và nó có hàm url()". */
if ( ! class_exists( 'VHG_Trang' ) ) {
	eval( 'class VHG_Trang { public static function url() { return "http://example.test/?vhg=app"; } }' );
}
t( 'chưa cài plugin Cổng -> vẫn dò được các trang', count( VHNB_Trang::ds_trang_khac() ) >= 3 );
$_map_du = array();
foreach ( VHNB_Trang::ds_trang_khac() as $_x ) { $_map_du[ $_x['ten'] ] = $_x['url']; }
t( '🔴 nhánh dự phòng CÓ trang Ghế', isset( $_map_du['Ghế massage'] ), array_keys( $_map_du ) );
t( 'có trang Chấm công',        isset( $_map_du['Chấm công'] ), array_keys( $_map_du ) );
t( 'có trang Vận hành chi phí', isset( $_map_du['Vận hành chi phí'] ), array_keys( $_map_du ) );
t( '🔴 KHÔNG có nút trỏ về chính trang đang đứng',
	! in_array( VHNB_Trang::url(), array_values( $_map_du ), true ), $_map_du );
/* Chưa cài Thư viện hợp đồng thì đừng dựng nút cho nó — liên kết chết còn tệ hơn không có nút. */
t( 'plugin chưa cài thì KHÔNG dựng nút', ! isset( $_map_du['Thư viện hợp đồng'] ), array_keys( $_map_du ) );

/* ---- nhánh CHÍNH: đọc bảng trang của plugin Cổng K&H ---- */
define( 'VHTC_VERSION', 'test' );
define( 'VHTC_DIR', $goc . '/wordpress/vhcp-trang-chu/' );
require_once VHTC_DIR . 'includes/class-vhtc-trang.php';
t( 'nạp được plugin Cổng K&H', class_exists( 'VHTC_Trang' ) && method_exists( 'VHTC_Trang', 'ds_app' ) );

$_ds_ch = VHNB_Trang::ds_trang_khac();
$_map   = array();
foreach ( $_ds_ch as $_x ) { $_map[ $_x['ten'] ] = $_x['url']; }
t( '🔴 đọc bảng trang -> CÓ Ghế Massage', isset( $_map['Ghế Massage'] ), array_keys( $_map ) );
t( 'và có Chấm Công',        isset( $_map['Chấm Công'] ), array_keys( $_map ) );
t( 'và có Vận Hành Chi Phí', isset( $_map['Vận Hành Chi Phí'] ), array_keys( $_map ) );
/* 🔴 BỎ CHÍNH TRANG NÀY RA. Bảng trang có mục "Nội Bộ" — một cái nút trỏ về đúng trang đang
   đứng thì vô nghĩa, và người dùng bấm vào tưởng mình đi đâu đó rồi mới thấy vẫn ở chỗ cũ. */
t( '🔴 bỏ chính trang Nội bộ ra khỏi danh sách',
	! in_array( VHNB_Trang::url(), array_values( $_map ), true ), $_map );
/* Plugin chưa cài -> `ds_app` trả mục có url rỗng. Lọt một mục như thế ra là một cái nút bấm
   vào không đi đâu cả. */
$_rong = 0;
foreach ( $_ds_ch as $_x ) { if ( '' === trim( (string) $_x['url'] ) ) { $_rong++; } }
teq( '🔴 KHÔNG lọt mục nào có địa chỉ rỗng (nút bấm vào không đi đâu)', 0, $_rong );
t( 'plugin Hợp đồng chưa cài thì không có trong danh sách',
	! isset( $_map['Thư Viện Hợp Đồng'] ), array_keys( $_map ) );

/* Và trang phải VẼ RA mấy cái nút ấy, không phải chỉ tính ra rồi bỏ đó. */
$_COOKIE = array();
ob_start(); VHNB_Trang::ve( null ); $_h_nut = ob_get_clean();
t( '🔴 màn chưa đăng nhập vẽ ra nút sang trang Ghế',
	false !== strpos( $_h_nut, 'Ghế Massage</a>' ), substr( $_h_nut, -2200 ) );

/* ==================================================================================
   BỘ NỐI PHIÊN DÙNG CHUNG (`VHCC_Phien`) — thứ trang MỚI sẽ cắm vào

   Anh Thắng 30/08/2026: *"sau trang mới thì dùng chung hết, thiết lập sẵn luôn"*.

   🔴 THỬ Ở ĐÂY chứ không ở `test-cham-cong.php`: bài này là bài duy nhất dựng được cảnh THẬT —
      một trang của plugin KHÁC gọi sang lõi phiên của plugin chấm công. Lõi mà chỉ được thử
      trong chính plugin của nó thì không ai biết nó có dùng nổi từ bên ngoài hay không.
   ================================================================================== */

vhnb_dung_bang();
$_GET = array(); $_POST = array(); $_COOKIE = array();
$GLOBALS['VHCP_CHUYEN'] = '';

t( 'có lớp VHCC_Phien', class_exists( 'VHCC_Phien' ) );
teq( 'hệ sẵn sàng', true, VHCC_Phien::co() );
teq( 'chưa đăng nhập -> thẻ rỗng', '', VHCC_Phien::the() );
teq( 'chưa đăng nhập -> toi() null', null, VHCC_Phien::toi() );

$nguon_cu2 = get_option( 'vhcc_nguon_nguoidung' );
update_option( 'vhcc_nguon_nguoidung', 'chung' );
$bang_cfg2 = $wpdb->prefix . 'vhcp_cfg';
$wpdb->exec_raw( "DELETE FROM $bang_cfg2 WHERE bang='CH_NguoiDung'" );
$wpdb->insert( $bang_cfg2, array( 'bang' => 'CH_NguoiDung', 'stt' => 1,
	'cols' => wp_json_encode( array( 'Chị Lõi Phiên', '445566', 'Quản lý', 'CS_VIVO' ) ) ) );
VHCC_Auth::mo_khoa();

VHCC_Web::$cookie_da_dat = array();
$r_p = VHCC_Phien::vao( '445566' );
t( 'vao() nhận PIN đúng', ! empty( $r_p['ok'] ), $r_p );
teq( 'và đặt cookie phiên', array( 'dat' ), VHCC_Web::$cookie_da_dat );

VHCC_Auth::mo_khoa();
$r_p2 = VHCC_Phien::vao( '000009' );
t( 'vao() chối PIN sai', empty( $r_p2['ok'] ) && '' !== $r_p2['loi'], $r_p2 );
t( '🔴 câu báo KHÔNG chứa PIN vừa gõ', false === strpos( (string) $r_p2['loi'], '000009' ), $r_p2 );

/* 🔴 MỘT BỘ CHỐT, KHÔNG PHẢI HAI. `vao()` phải đi qua đúng `VHCC_Auth::login()` — tự so PIN lấy
   là hệ có hai bộ đếm nhập sai rời nhau, và kẻ dò PIN bị khoá ở cửa này cứ sang cửa kia dò tiếp. */
VHCC_Auth::mo_khoa();
for ( $i_p = 0; $i_p < 10; $i_p++ ) { VHCC_Phien::vao( '111119' ); }
$kq_p = VHCC_Auth::login( '445566' );
t( '🔴 dò sai 10 lượt qua VHCC_Phien thì cửa VHCC_Auth khoá theo (một bộ chốt)',
	empty( $kq_p['ok'] ) && false !== strpos( (string) $kq_p['error'], '10 phút' ), $kq_p );
VHCC_Auth::mo_khoa();

/* ---- chữ ký: mỗi trang một không gian ---- */
$TOK_P = VHCC_Auth::phat_token( 'Chị Lõi Phiên', 'Quản lý', 'CS_VIVO', 'NV777' );
$_COOKIE[ constant( 'VHCC_Web::COOKIE' ) ] = $TOK_P;
teq( 'the() đọc đúng thẻ trong cookie', $TOK_P, VHCC_Phien::the() );
t( 'toi() nhận ra người đang đăng nhập', is_array( VHCC_Phien::toi() ), VHCC_Phien::toi() );

/* ⛔ MÃ TƯƠNG ĐƯƠNG CÓ CHỦ Ý — nói ra để đời sau khỏi đi tìm phép thử không tồn tại.
   `VHNB_Trang::dang_nhap()` gọi `VHCC_Phien::vao()`, và có ĐƯỜNG LUI chạy đúng những việc ấy
   khi plugin Chấm công còn là bản cũ chưa có lõi chung. Vì đường lui làm y hệt, tắt lõi đi thì
   hành vi không đổi — phá thử "Nội bộ không dùng lõi chung" luôn sống, và đó là điều ĐÚNG.
   Đường lui giữ vì trang Nội bộ nay là TRANG CHỦ: cài Nội bộ mới mà quên cập nhật Chấm công
   thì cả công ty mất đường đăng nhập, chứ không phải mất một tính năng phụ.
   Thứ PHẢI canh không phải "có gọi lõi không", mà là HAI BẢN KHÔNG ĐƯỢC LỆCH: */
teq( '🔴 hai đường cho ra CÙNG một câu chối khi PIN sai khuôn',
	(string) VHCC_Phien::vao( 'abc' )['loi'], (string) VHNB_Trang::dang_nhap( 'abc' )['loi'] );
t( '🔴 chữ ký của hai trang KHÁC NHAU (cắt biểu mẫu trang này dán sang trang kia là hỏng)',
	VHCC_Phien::chu_ky( 'vhnb' ) !== VHCC_Phien::chu_ky( 'vhxx' ) );
/* 🔴 HAI BẢN KHÔNG ĐƯỢC LỆCH. Trang Nội bộ tự tính chữ ký của nó (cố ý — xem ghi chú ở
   `VHNB_Trang::chu_ky`). Lệch một chữ là mọi biểu mẫu đang mở trên máy người dùng hỏng hết, mà
   không bài kiểm nào kêu. */
teq( '🔴 chữ ký Nội bộ khớp đúng chữ ký bộ nối cùng không gian',
	VHNB_Trang::chu_ky( $TOK_P ), VHCC_Phien::chu_ky( 'vhnb', $TOK_P ) );
teq( 'the_phien() của Nội bộ khớp the() của bộ nối', VHNB_Trang::the_phien(), VHCC_Phien::the() );

$_POST = array( 'ky' => VHCC_Phien::chu_ky( 'vhxx' ) );
t( 'ky_dung: chữ ký đúng không gian thì nhận', VHCC_Phien::ky_dung( 'vhxx' ) );
t( '🔴 chữ ký của không gian KHÁC thì chối', ! VHCC_Phien::ky_dung( 'vhnb' ) );
$_POST = array();

/* ---- xu_post: một lời gọi cho cả đăng nhập lẫn thoát ---- */
$_COOKIE = array();
VHCC_Auth::mo_khoa();
$_POST = array( 'viec' => 'dang_nhap', 'pin' => '445566' );
$r_xp = VHCC_Phien::xu_post( 'vhxx' );
$_POST = array();
teq( 'xu_post nhận lượt đăng nhập', 'vao', (string) $r_xp['viec'] );
t( 'và báo vào được', ! empty( $r_xp['ok'] ), $r_xp );

/* 🔴 KHÔNG TỰ CHUYỂN HƯỚNG — nơi gọi quyết định đi đâu. Hàm có `wp_safe_redirect` + `exit` thì
   bài kiểm gọi nó là bài kiểm tự chết giữa đường. */
teq( '🔴 xu_post KHÔNG tự chuyển hướng', '', (string) $GLOBALS['VHCP_CHUYEN'] );

$TOK_P2 = VHCC_Auth::phat_token( 'Chị Lõi Phiên', 'Quản lý', 'CS_VIVO', 'NV777' );
$_COOKIE[ constant( 'VHCC_Web::COOKIE' ) ] = $TOK_P2;
$_POST = array( 'viec' => 'thoat', 'ky' => 'ky-gia' );
$r_xr = VHCC_Phien::xu_post( 'vhxx' );
$_POST = array();
teq( '🔴 thoát mà chữ ký sai thì KHÔNG làm gì', '', (string) $r_xr['viec'] );
t( 'và thẻ vẫn còn sống', null !== VHCC_Auth::user_by_token( $TOK_P2 ) );

$_POST = array( 'viec' => 'thoat', 'ky' => VHCC_Phien::chu_ky( 'vhxx' ) );
$r_xr2 = VHCC_Phien::xu_post( 'vhxx' );
$_POST = array();
teq( 'chữ ký đúng thì thoát', 'ra', (string) $r_xr2['viec'] );
teq( '🔴 và thẻ bị HUỶ hẳn, không chỉ xoá cookie', null, VHCC_Auth::user_by_token( $TOK_P2 ) );

/* ---- hai mảnh HTML sẵn dùng ---- */
$_COOKIE = array();
$h_op = VHCC_Phien::o_pin( array( 'loi' => 'PIN không đúng.' ) );
t( 'o_pin có ô nhập và tên việc',
	false !== strpos( $h_op, 'name="pin"' ) && false !== strpos( $h_op, 'value="dang_nhap"' ), $h_op );
t( '🔴 o_pin là type=password, PIN không hiện lên màn hình',
	false !== strpos( $h_op, 'type="password"' ), $h_op );
t( 'o_pin in ra câu báo lỗi được truyền vào', false !== strpos( $h_op, 'PIN không đúng.' ), $h_op );
$h_op2 = VHCC_Phien::o_pin();
t( 'không truyền lỗi thì không dựng khối báo rỗng', false === strpos( $h_op2, 'bao loi' ), $h_op2 );
/* 🔴 GÕ SAI THÌ Ô VỀ TRỐNG. Trả lại giá trị vừa gõ là PIN nằm nguyên trong mã nguồn trang gửi
   xuống máy người ta — ảnh chụp màn hình đi khắp nơi, và một ảnh đã làm mất một khoá cầu nối. */
$_POST = array( 'viec' => 'dang_nhap', 'pin' => '778811' );
$h_op3 = VHCC_Phien::o_pin( array( 'loi' => 'PIN không đúng.' ) );
$_POST = array();
t( '🔴 o_pin KHÔNG trả lại PIN vừa gõ vào ô', false === strpos( $h_op3, '778811' ), $h_op3 );

$_COOKIE[ constant( 'VHCC_Web::COOKIE' ) ] = $TOK_P;
$h_nt = VHCC_Phien::nut_thoat( 'vhxx' );
t( '🔴 nút Thoát là FORM POST có chữ ký, không phải đường dẫn',
	false !== strpos( $h_nt, '<form method="post"' ) && false !== strpos( $h_nt, 'name="ky"' )
	&& false !== strpos( $h_nt, 'value="thoat"' ) && false === strpos( $h_nt, '<a ' ), $h_nt );
t( 'và mang đúng chữ ký của không gian truyền vào',
	false !== strpos( $h_nt, VHCC_Phien::chu_ky( 'vhxx' ) ), $h_nt );

$wpdb->exec_raw( "DELETE FROM $bang_cfg2 WHERE bang='CH_NguoiDung'" );
update_option( 'vhcc_nguon_nguoidung', $nguon_cu2 ? $nguon_cu2 : 'ho_so' );
$_GET = array(); $_POST = array(); $_COOKIE = array();
vhnb_dung_bang();

/* ================================================================= kết */

echo "\n=== KIỂM TRANG NỘI BỘ ===\n";
foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
echo 'ĐẠT: ' . $dat . '   TRƯỢT: ' . count( $truot ) . "\n";
exit( $truot ? 1 : 0 );
