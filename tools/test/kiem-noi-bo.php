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
	array( 'u', 'noi_dung', 'nhom', 'nhom_id' ), $ten_ts );

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
	$wpdb->insert( VHNB_DB::t( 'tim' ),
		array( 'bai_id' => $B1, 'ma_nv' => 'NV001', 'tao_luc' => current_time( 'mysql' ) ) );
} catch ( Exception $e ) {
	/* $wpdb thật trả false; bản giả chạy trên PDO thì ném. Bắt cả hai — thứ đang kiểm là RÀNG
	   BUỘC CỦA BẢNG, không phải cách thư viện báo lỗi. */
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

ob_start(); VHNB_Trang::ve( null ); $h_ra = ob_get_clean();
t( 'chưa đăng nhập -> mời sang trang chấm công đăng nhập', false !== strpos( $h_ra, 'Tới trang đăng nhập' ) );
t( 'và KHÔNG có ô đăng bài', false === strpos( $h_ra, 'name="noi_dung"' ) );
t( 'và KHÔNG hỏi PIN ở đây (một cửa PIN thôi, không mở cửa thứ hai)',
	false === strpos( $h_ra, 'name="pin"' ), $h_ra );

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
t( '🔴 không có thẻ <script> nào lọt ra', false === stripos( $h, '<script' ), 'CÓ <script> TRONG TRANG' );
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

teq( 'chưa khai công ty thì KHÔNG vẽ khung trống', '', VHCC_Cty::html() );
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
/* Chân trang là CHỮ — không được kéo theo script hay thuộc tính on*= nào. */
t( 'chân trang không mang script nào',
	stripos( $h_c, '<script' ) === false && ! preg_match( '/\son[a-z]+\s*=\s*["\']/i', $h_c ) );
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
foreach ( array( 'vao', 'dang', 'nhom' ) as $_v ) {
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
teq( 'việc không đụng tới thì giữ nguyên', 'NHAN_VIEN', VHNB_Quyen::cai_dat()['vao'] );

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

/* ---- chốt "vào trang" ---- */
VHNB_Quyen::dat( array( 'vao' => 'QUAN_LY' ) );
ob_start(); VHNB_Trang::ve( $U_QCHT ); $_h_chan = ob_get_clean();
t( '🔴 chưa đủ bậc thì KHÔNG vẽ bảng tin ra', false === strpos( $_h_chan, 'class="giua"' ), $_h_chan );
t( 'và nói rõ cần bậc nào', false !== strpos( $_h_chan, 'Quản lý' ), $_h_chan );
ob_start(); VHNB_Trang::ve( $U_QQL ); $_h_vao = ob_get_clean();
t( 'đủ bậc thì vào bình thường', false !== strpos( $_h_vao, 'class="giua"' ) );

delete_option( VHNB_Quyen::O );
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
/* 🔴 Chuông KHÔNG được dùng script — cùng luật với màn quản trị chấm công. */
t( '🔴 chuông không dùng một dòng script nào',
	false === stripos( $_h_ch, '<script' ) && ! preg_match( '/\son[a-z]+\s*=\s*["\']/i', $_h_ch ), 'có script' );
/* ⚠️ Mở trang KHÔNG được tự đánh dấu đã đọc: tải lại trang là con số về 0 dù chưa ai mở chuông. */
t( '🔴 mở trang KHÔNG tự đánh dấu đã đọc', VHNB_Bao::chua_doc( 'NV001' ) > 0 );

/* Người chưa có Mã NV thì hộp thư không có địa chỉ — nói thẳng, đừng treo chuông rỗng. */
$_U_KM = VHCC_Auth::user_by_token( VHCC_Auth::phat_token( 'Không Mã', 'Nhân viên', 'CS_VIVO', '' ) );
teq( 'không có mã thì đếm bằng 0, không nổ', 0, VHNB_Bao::chua_doc( '' ) );
teq( 'và đánh dấu đọc cũng không đụng vào gì', 0, VHNB_Bao::danh_dau_doc( '' ) );

vhnb_dung_bang();

/* ================================================================= đường dẫn của trang */

teq( 'đường dẫn mặc định là noi-bo', 'noi-bo', VHNB_Trang::slug() );
update_option( 'vhnb_slug', 'noi-bo-cong-ty' );
teq( 'đổi được đường dẫn', 'noi-bo-cong-ty', VHNB_Trang::slug() );
update_option( 'vhnb_slug', '' );
teq( 'để trống thì về mặc định, KHÔNG để rỗng (rỗng là trang không có địa chỉ)',
	'noi-bo', VHNB_Trang::slug() );

/* ================================================================= kết */

echo "\n=== KIỂM TRANG NỘI BỘ ===\n";
foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
echo 'ĐẠT: ' . $dat . '   TRƯỢT: ' . count( $truot ) . "\n";
exit( $truot ? 1 : 0 );
