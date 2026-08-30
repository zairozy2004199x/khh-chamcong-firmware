<?php
/**
 * KIỂM HỆ DỰ ÁN & TIẾN ĐỘ (wordpress/vhcp-du-an).
 *
 * =============================================================================================
 * 🔴 VÌ SAO PHẢI KIỂM RIÊNG.
 * =============================================================================================
 * Đây là hệ QUY TRÌNH: giá trị của nó nằm ở chỗ NÓ TỪ CHỐI cái gì, không phải ở chỗ nó cho
 * bấm cái gì. Một hệ quy trình cho nhảy cóc, cho bàn giao khi chưa có ngày, cho bên Kỹ thuật
 * sửa tiến độ của bên Marketing — thì nó chỉ còn là một cái bảng ghi chép, và người ta sẽ quay
 * lại hỏi nhau qua điện thoại như cũ.
 *
 * Nên bài này canh nặng nhất vào ba chỗ: LUẬT ĐI GIỮA CÁC CHẶNG · CHỐT QUYỀN · và chỗ NỐI TIỀN
 * (nơi một con số sai đi thẳng vào báo cáo).
 *
 * Chạy: php tools/test/kiem-du-an.php
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

/* ================================================================= nạp */

define( 'VHDA_VERSION', 'test' );
define( 'VHDA_DIR', $goc . '/wordpress/vhcp-du-an/' );

/* ĐỌC danh sách lớp từ CHÍNH tệp plugin, không gõ tay lại — thêm một lớp vào plugin mà bài kiểm
   không nạp là lỗi của BÀI KIỂM trông y như lỗi của plugin. Cùng lối với `kiem-noi-bo.php`. */
$chinh = file_get_contents( VHDA_DIR . 'vhcp-du-an.php' );
preg_match_all( "#require_once VHDA_DIR \. '(includes/class-vhda-[a-z-]+\.php)';#", $chinh, $m_lop );
t( 'đọc được danh sách lớp trong vhcp-du-an.php', count( $m_lop[1] ) >= 5, $m_lop[1] );
foreach ( $m_lop[1] as $duong ) { require_once VHDA_DIR . $duong; }

function vhda_dung_bang() {
	global $wpdb;
	foreach ( VHDA_DB::bang() as $ten => $than ) {
		$wpdb->exec_raw( 'DROP TABLE IF EXISTS ' . VHDA_DB::t( $ten ) );
		$wpdb->exec_raw( vhcc_test_ddl( VHDA_DB::t( $ten ), $than ) );
	}
}
vhda_dung_bang();

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * PHẦN 1 — LUẬT ĐI GIỮA CÁC CHẶNG. Hàm thuần, chạy được TRƯỚC khi nạp bất cứ plugin nào khác.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */

teq( 'bảy chặng trong dãy', 7, count( VHDA_Luong::DAY ) );
t( 'huỷ KHÔNG nằm trong dãy', VHDA_Luong::vi_tri( VHDA_Luong::HUY ) === -1 );
t( 'mã lạ cũng không nằm trong dãy', VHDA_Luong::vi_tri( 'bia_ra' ) === -1 );
t( 'mọi chặng trong dãy đều có tên hiện ra màn', ( function () {
	foreach ( VHDA_Luong::DAY as $c ) { if ( VHDA_Luong::ten( $c ) === $c ) { return false; } }
	return true;
} )() );
t( 'và đều có câu nói rõ chờ ai làm gì', ( function () {
	foreach ( VHDA_Luong::DAY as $c ) { if ( '' === VHDA_Luong::cho( $c ) ) { return false; } }
	return true;
} )() );

/* --- ĐI TỚI TỪNG BƯỚC --- */
teq( 'đi đúng một bước: được', '',
	VHDA_Luong::vi_sao_khong_di( VHDA_Luong::HOP_DONG, VHDA_Luong::PHUONG_AN ) );
$_loi = VHDA_Luong::vi_sao_khong_di( VHDA_Luong::HOP_DONG, VHDA_Luong::MO_CUA );
t( '🔴 NHẢY CÓC bị chặn', '' !== $_loi, $_loi );
/* Câu chối phải KỂ TÊN mấy chặng đang bị bỏ qua — "không hợp lệ" thì người dùng không biết
   mình thiếu bước nào, và sẽ bấm lại đúng cái nút ấy thêm mấy lần nữa. */
t( 'và câu chối KỂ TÊN chặng bị bỏ qua',
	false !== mb_strpos( $_loi, 'Lên phương án' ) && false !== mb_strpos( $_loi, 'Bàn giao' ), $_loi );

/* --- LÙI THÌ LÙI XA ĐƯỢC --- */
teq( 'lùi một bước: được', '',
	VHDA_Luong::vi_sao_khong_di( VHDA_Luong::THI_CONG, VHDA_Luong::BAN_GIAO ) );
teq( '🔴 lùi XA cũng được (khách đổi ý, quay lại phương án)', '',
	VHDA_Luong::vi_sao_khong_di( VHDA_Luong::MO_CUA, VHDA_Luong::PHUONG_AN ) );

/* --- HUỶ ĐỨNG NGOÀI DÃY --- */
teq( 'huỷ từ giữa chừng: được', '',
	VHDA_Luong::vi_sao_khong_di( VHDA_Luong::THI_CONG, VHDA_Luong::HUY ) );
teq( 'huỷ từ chặng đầu: cũng được', '',
	VHDA_Luong::vi_sao_khong_di( VHDA_Luong::HOP_DONG, VHDA_Luong::HUY ) );
teq( 'mở lại từ huỷ về chặng đang dở: được', '',
	VHDA_Luong::vi_sao_khong_di( VHDA_Luong::HUY, VHDA_Luong::THI_CONG ) );
t( 'đang ở đâu chuyển vào đúng đó thì chối',
	'' !== VHDA_Luong::vi_sao_khong_di( VHDA_Luong::THI_CONG, VHDA_Luong::THI_CONG ) );
t( 'chặng đích không có thật thì chối',
	'' !== VHDA_Luong::vi_sao_khong_di( VHDA_Luong::THI_CONG, 'bia_ra' ) );

/* --- THANH TIẾN ĐỘ --- */
teq( 'chặng đầu: 0%',   0,   VHDA_Luong::phan_tram( VHDA_Luong::HOP_DONG ) );
teq( 'chặng cuối: 100%', 100, VHDA_Luong::phan_tram( VHDA_Luong::XONG ) );
/* 🔴 Dự án ĐÃ HUỶ mà thanh tiến độ vẫn xanh 70% là mắt đọc nhầm nó đang chạy. */
teq( '🔴 đã huỷ: 0%, không giữ phần trăm của chặng cũ', 0, VHDA_Luong::phan_tram( VHDA_Luong::HUY ) );
teq( 'kế tiếp của chặng cuối là rỗng', '', VHDA_Luong::ke_tiep( VHDA_Luong::XONG ) );
teq( 'kế tiếp của huỷ cũng rỗng', '', VHDA_Luong::ke_tiep( VHDA_Luong::HUY ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * PHẦN 2 — CHƯA CÀI PLUGIN CHẤM CÔNG
 * 🔴 Phải chạy TRƯỚC khi nạp nó: `class_exists` một khi đã true thì true mãi.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */

$U_TAM = array( 'name' => 'Ai Đó', 'role' => 'Nhân viên', 'maNV' => 'NV000' );
t( '🔴 thiếu bộ đo bậc thì CHO QUA, không chối sạch mọi người',
	VHDA_Quyen::duoc( $U_TAM, 'lap' ) );
t( 'việc không có tên thì vẫn chối', ! VHDA_Quyen::duoc( $U_TAM, 'viec_bia_ra' ) );
teq( 'chưa cài chấm công -> không tra được bộ phận, trả rỗng', '', VHDA_Quyen::bo_phan_cua( $U_TAM ) );
t( 'chưa cài chi phí -> co_he_chi_phi = false', ! VHDA_Tien::co_he_chi_phi() );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * PHẦN 3 — NẠP CẢ HỆ, RỒI CHẠY QUY TRÌNH THẬT
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */

vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );
vhcc_test_boot( $goc . '/wordpress/vhcp-cham-cong' );
vhcp_test_dat_gio( '2026-09-01 08:00:00' );
global $wpdb;

t( 'nạp xong thì có bộ đo bậc', class_exists( 'VHCC_Vai' ) );
t( 'và có hệ chi phí để hỏi', VHDA_Tien::co_he_chi_phi() );

$U_AD  = array( 'name' => 'Anh Thắng',  'role' => 'Admin',    'maNV' => 'AD001' );
$U_QL  = array( 'name' => 'Chị Quản Lý','role' => 'Quản lý',  'maNV' => 'QL001' );
$U_NV  = array( 'name' => 'Em Kỹ Thuật','role' => 'Nhân viên','maNV' => 'KT001' );
$U_NV2 = array( 'name' => 'Em Mkt',     'role' => 'Nhân viên','maNV' => 'MK001' );

/* --- BẬC VAI --- */
t( 'Nhân viên KHÔNG lập được dự án', ! VHDA_Quyen::duoc( $U_NV, 'lap' ) );
t( 'Quản lý lập được',                 VHDA_Quyen::duoc( $U_QL, 'lap' ) );
t( '🔴 Nhân viên VẪN cập nhật được tiến độ (việc của người làm, không phải người quản)',
	VHDA_Quyen::duoc( $U_NV, 'tien_do' ) );
t( 'Quản lý KHÔNG huỷ được dự án (chỉ Admin)', ! VHDA_Quyen::duoc( $U_QL, 'huy' ) );
t( 'Admin huỷ được', VHDA_Quyen::duoc( $U_AD, 'huy' ) );
$_ly = VHDA_Quyen::vi_sao_khong( $U_NV, 'lap' );
t( 'câu chối nói rõ cần vai nào', false !== mb_strpos( $_ly, 'Quản lý' ), $_ly );

/* --- LẬP DỰ ÁN --- */
$r = VHDA_DuAn::lap( $U_NV, array( 'ten' => 'Thử' ) );
t( '🔴 Nhân viên lập dự án: bị chối ở LÕI, không chỉ ẩn nút', empty( $r['ok'] ), $r );

$r = VHDA_DuAn::lap( $U_QL, array( 'ten' => '' ) );
t( 'tên rỗng thì chối', empty( $r['ok'] ), $r );

$r = VHDA_DuAn::lap( $U_QL, array( 'ten' => 'Gian hàng GO Dĩ An', 'coso' => 'GO DĨ AN',
	'khach' => 'Central Retail', 'so_hop_dong' => 'HD-2026-88', 'gia_tri' => 500000000 ) );
t( 'Quản lý lập được dự án', ! empty( $r['ok'] ), $r );
$MA = $r['ma'];
$ID = (int) $r['id'];
$d  = VHDA_DuAn::mot( $MA );
teq( 'dự án mới nằm ở chặng đầu', VHDA_Luong::HOP_DONG, (string) $d['chang'] );
t( 'và có ngay một dòng nhật ký', count( VHDA_DuAn::nhat_ky_cua( $ID ) ) >= 1 );

/* --- CHƯA CHỐT NGÀY THÌ CHƯA BÀN GIAO --- */
$r = VHDA_DuAn::chuyen( $U_QL, $MA, VHDA_Luong::PHUONG_AN );
t( 'sang phương án: được', ! empty( $r['ok'] ), $r );
$r = VHDA_DuAn::chuyen( $U_QL, $MA, VHDA_Luong::CHOT_NGAY );
t( 'sang chốt ngày: được', ! empty( $r['ok'] ), $r );
$r = VHDA_DuAn::chuyen( $U_QL, $MA, VHDA_Luong::BAN_GIAO );
t( '🔴 CHƯA CHỐT NGÀY thì chưa bàn giao được', empty( $r['ok'] ), $r );
t( 'và nói rõ vì sao', false !== mb_strpos( (string) $r['loi'], 'Chưa chốt ngày' ), $r );

/* --- CHỐT NGÀY --- */
$r = VHDA_DuAn::chot_ngay( $U_QL, $MA, '2026-09-10', '2026-09-05' );
t( '🔴 mở cửa TRƯỚC thi công: bị chối', empty( $r['ok'] ), $r );
$r = VHDA_DuAn::chot_ngay( $U_QL, $MA, '10/09/2026', '2026-09-20' );
t( 'ngày sai khuôn: bị chối', empty( $r['ok'] ), $r );
$r = VHDA_DuAn::chot_ngay( $U_QL, $MA, '2026-09-10', '2026-09-20' );
t( 'chốt ngày hợp lệ: được', ! empty( $r['ok'] ), $r );
$r = VHDA_DuAn::chot_ngay( $U_NV, $MA, '2026-09-10', '2026-09-20' );
t( 'Nhân viên chốt ngày: bị chối', empty( $r['ok'] ), $r );

$r = VHDA_DuAn::chuyen( $U_QL, $MA, VHDA_Luong::BAN_GIAO );
t( 'chốt ngày rồi thì bàn giao được', ! empty( $r['ok'] ), $r );

/* --- BÀN GIAO --- */
$r = VHDA_DuAn::giao( $U_NV, $MA, 'Kỹ thuật' );
t( 'Nhân viên KHÔNG bàn giao được', empty( $r['ok'] ), $r );
$r = VHDA_DuAn::giao( $U_QL, $MA, '' );
t( 'bộ phận rỗng: chối', empty( $r['ok'] ), $r );
$r = VHDA_DuAn::giao( $U_QL, $MA, 'Kỹ thuật', 'Dựng khung + điện', '2026-09-25' );
t( '🔴 hạn MUỘN HƠN ngày mở cửa: chối', empty( $r['ok'] ), $r );
t( 'và nói rõ hai ngày đang đá nhau',
	false !== mb_strpos( (string) $r['loi'], '2026-09-20' ), $r );

$r = VHDA_DuAn::giao( $U_QL, $MA, 'Kỹ thuật', 'Dựng khung + điện', '2026-09-18' );
t( 'bàn giao Kỹ thuật: được', ! empty( $r['ok'] ), $r );
$ID_KT = (int) $r['id'];
$r = VHDA_DuAn::giao( $U_QL, $MA, 'Marketing', 'Bảng hiệu + khai trương' );
t( 'bàn giao Marketing: được', ! empty( $r['ok'] ), $r );
$ID_MK = (int) $r['id'];

/* 🔴 GIAO LẠI CÙNG BỘ PHẬN = CẬP NHẬT, KHÔNG ĐẺ DÒNG THỨ HAI. Hai dòng cùng bộ phận thì tiến
   độ trung bình tính sai, và không ai biết phải cập nhật dòng nào. */
$r = VHDA_DuAn::giao( $U_QL, $MA, 'Kỹ thuật', 'Dựng khung + điện + nước', '2026-09-18' );
t( 'giao lại cùng bộ phận: được', ! empty( $r['ok'] ), $r );
teq( '🔴 và KHÔNG đẻ dòng thứ hai', 2, count( VHDA_DuAn::viec_cua( $ID ) ) );
teq( 'mà cập nhật đúng dòng cũ', $ID_KT, (int) $r['id'] );

/* --- TIẾN ĐỘ CHUNG --- */
teq( '🔴 chưa giao cho ai thì trả null, KHÔNG trả 0', null, VHDA_DuAn::tien_do_chung( array() ) );
teq( 'giao rồi mà chưa ai làm gì thì mới là 0', 0, VHDA_DuAn::tien_do_chung( VHDA_DuAn::viec_cua( $ID ) ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * PHẦN 4 — CẬP NHẬT TIẾN ĐỘ: GÁC THEO BỘ PHẬN, KHÔNG CHỈ THEO BẬC VAI
 * 🔴 Bên Kỹ thuật sửa được tiến độ của bên Marketing là con số ấy hết nghĩa — ai cũng vào được
 *    thì không ai chịu trách nhiệm về nó nữa.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */

/* ═══════════════════════════════════════════════════════════════════════════════════════════
   🔴 HAI KHÁI NIỆM "BỘ PHẬN", ĐỪNG LẪN
   ═══════════════════════════════════════════════════════════════════════════════════════════
   1. BỘ PHẬN TÍNH LƯƠNG (`VHCC_Luong::BP_DS`) — đúng bốn cái: Máy tự động · Khu vui chơi · Văn
      phòng · Part time. Gắn với CƠ SỞ, sinh ra để chọn cách tính công. "Kỹ thuật" KHÔNG có
      trong đó.
   2. BỘ PHẬN CÔNG VIỆC — Kỹ thuật, Marketing… khai ở bảng người dùng của plugin Chi phí, gắn
      với TỪNG NGƯỜI. Đây mới là thứ anh Thắng nói khi bảo "bàn giao xuống từng bộ phận".

   Bản đầu của bài kiểm này ghi `'bo_phan' => 'Kỹ thuật'` vào hồ sơ nhân sự — một cột không tồn
   tại. Rồi bản thứ hai hỏi bộ phận theo CƠ SỞ, và nhận về "Chưa xếp" cho mọi người vì "Kỹ
   thuật" không nằm trong bốn cái kia. Cả hai lần đều không báo lỗi gì, chỉ lặng lẽ chối. */
$_u_cu = VHCP_Cfg::read( VHCP_Cfg::USER );
VHCP_Cfg::write( VHCP_Cfg::USER, array(
	/* cột: tên · pin · vai · cơ sở · tk có · mã đt · BỘ PHẬN · đơn vị · xem đơn vị */
	array( 'Em Kỹ Thuật', '445511', 'Nhân viên', '', '', '', 'Kỹ thuật',  '', '' ),
	array( 'Em Mkt',      '445522', 'Nhân viên', '', '', '', 'Marketing', '', '' ),
) );

teq( '🔴 bộ phận CÔNG VIỆC lấy từ bảng người dùng Chi phí', 'Kỹ thuật',
	VHDA_Quyen::bo_phan_cua( $U_NV ) );
teq( 'và của người kia là Marketing', 'Marketing', VHDA_Quyen::bo_phan_cua( $U_NV2 ) );

/* NGUỒN LUI: không có tên trong bảng ấy thì rơi về bộ phận theo CƠ SỞ (bốn bộ phận tính lương)
   — giữ để cơ sở đã xếp sẵn vẫn dùng được ngay, không phải khai lại từng người. */
$wpdb->insert( VHCC_DB::t( 'bo_phan_coso' ), array( 'coso' => 'VP K&H', 'bo_phan' => 'Văn phòng' ) );
teq( 'không có trong bảng Chi phí -> rơi về bộ phận theo cơ sở', 'Văn phòng',
	VHDA_Quyen::bo_phan_cua( array( 'name' => 'Người Khác', 'role' => 'Nhân viên', 'coso' => 'VP K&H' ) ) );

/* 🔴 "Chưa xếp" KHÔNG phải một bộ phận. Trả nó ra như một tên bộ phận thì mọi người ở các cơ sở
   chưa xếp lại khớp với nhau, và họ sửa được tiến độ của nhau. */
teq( '🔴 cơ sở chưa xếp bộ phận -> trả rỗng, KHÔNG trả "Chưa xếp"', '',
	VHDA_Quyen::bo_phan_cua( array( 'name' => 'Người Lạ Hoắc', 'role' => 'Nhân viên', 'coso' => 'CƠ SỞ LẠ' ) ) );

$r = VHDA_DuAn::tien_do( $U_NV, $ID_KT, 40, 'xong phần khung' );
t( 'bên Kỹ thuật cập nhật phần của mình: được', ! empty( $r['ok'] ), $r );
$r = VHDA_DuAn::tien_do( $U_NV, $ID_MK, 90 );
t( '🔴 bên Kỹ thuật sửa phần của Marketing: BỊ CHỐI', empty( $r['ok'] ), $r );
t( 'và nói rõ mình thuộc bộ phận nào',
	false !== mb_strpos( (string) $r['loi'], 'Kỹ thuật' ), $r );

$r = VHDA_DuAn::tien_do( $U_QL, $ID_MK, 60 );
t( '🔴 Quản lý sửa được MỌI bộ phận (người trực tiếp làm có thể đang nghỉ)', ! empty( $r['ok'] ), $r );

/* Người chưa khai bộ phận: chối, và nói rõ phải nhờ ai khai. */
$U_LA = array( 'name' => 'Người Lạ', 'role' => 'Nhân viên', 'maNV' => 'ZZ999' );
$_l = VHDA_Quyen::vi_sao_khong_sua_tien_do( $U_LA, 'Kỹ thuật' );
t( '🔴 chưa khai bộ phận thì chối', '' !== $_l, $_l );
t( 'và chỉ đúng chỗ để khai', false !== mb_strpos( $_l, 'Quản lý nhân sự' ), $_l );

$r = VHDA_DuAn::tien_do( $U_NV, $ID_KT, 130 );
t( 'tiến độ ngoài khoảng 0..100: chối', empty( $r['ok'] ), $r );
$r = VHDA_DuAn::tien_do( $U_NV, $ID_KT, 100 );
t( 'đạt 100% thì được', ! empty( $r['ok'] ), $r );
$_v = VHDA_DuAn::viec_cua( $ID );
$_kt = null;
foreach ( $_v as $x ) { if ( (int) $x['id'] === $ID_KT ) { $_kt = $x; } }
teq( '🔴 100% thì tự đánh dấu XONG', 1, (int) $_kt['xong'] );
teq( 'tiến độ chung = trung bình hai bộ phận (100 + 60) / 2', 80,
	VHDA_DuAn::tien_do_chung( $_v ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * PHẦN 5 — HUỶ VÀ MỞ LẠI
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */

VHDA_DuAn::chuyen( $U_QL, $MA, VHDA_Luong::THI_CONG );
$r = VHDA_DuAn::chuyen( $U_QL, $MA, VHDA_Luong::HUY );
t( 'Quản lý KHÔNG huỷ được', empty( $r['ok'] ), $r );
$r = VHDA_DuAn::chuyen( $U_AD, $MA, VHDA_Luong::HUY );
t( 'Admin huỷ được', ! empty( $r['ok'] ), $r );
$d = VHDA_DuAn::mot( $MA );
teq( 'đang ở trạng thái huỷ', VHDA_Luong::HUY, (string) $d['chang'] );
teq( '🔴 và NHỚ chặng đang dở trước khi huỷ', VHDA_Luong::THI_CONG, (string) $d['chang_truoc'] );

$r = VHDA_DuAn::chuyen( $U_AD, $MA, '' );
t( 'mở lại: được', ! empty( $r['ok'] ), $r );
teq( '🔴 và quay về ĐÚNG chặng đang dở, không về đầu', VHDA_Luong::THI_CONG,
	(string) VHDA_DuAn::mot( $MA )['chang'] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * PHẦN 6 — NỐI TIỀN
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */

VHCP_Auth::dat_vai_tro( 'Admin', 'Admin' );
$_don = VHCP_Don::create_don( 'T9/2026 (1/9-6/9/2026)', 'Chị Quản Lý' );
$_mad = $_don['maDon'];
VHCP_Don::add_line( $_mad, array( 'coso' => 'GO DĨ AN', 'ngay' => '2026-09-02',
	'phanLoaiTT' => 'Thanh toán cá nhân', 'nhom' => 'Chi phí cơ sở', 'noiDung' => 'Vật tư',
	'soLuong' => 1, 'donGia' => 3000000, 'thanhTien' => 3000000 ) );

$r = VHDA_Tien::gan_don( $U_NV, $MA, $_mad );
t( 'Nhân viên KHÔNG gán được đơn', empty( $r['ok'] ), $r );
$r = VHDA_Tien::gan_don( $U_QL, $MA, 'D_KHONG_CO_THAT' );
t( '🔴 gán mã đơn không có thật: bị chối', empty( $r['ok'] ), $r );
$r = VHDA_Tien::gan_don( $U_QL, $MA, $_mad );
t( 'gán đơn có thật: được', ! empty( $r['ok'] ), $r );
$r = VHDA_Tien::gan_don( $U_QL, $MA, $_mad );
t( 'gán lại đúng đơn ấy: chối, không đẻ dòng thứ hai', empty( $r['ok'] ), $r );

$tg = VHDA_Tien::tong( $ID );
t( 'gom được tiền', ! empty( $tg['co'] ), $tg );
teq( 'đúng một dòng đơn', 1, count( $tg['dong'] ) );
t( 'và có số tiền thật', (int) $tg['thucChi'] > 0 || (int) $tg['tamUng'] > 0, $tg );

/* 🔴 ĐƠN ĐÃ GÁN MÀ BÊN KIA KHÔNG CÒN THẤY thì phải KÊU, không lặng lẽ bỏ qua — tổng sẽ thiếu
   đúng phần của đơn ấy mà không ai biết vì sao. */
$wpdb->insert( VHDA_DB::t( 'don' ), array( 'du_an_id' => $ID, 'ma_don' => 'D_DA_XOA' ) );
$tg = VHDA_Tien::tong( $ID );
teq( '🔴 kêu đúng một đơn mồ côi', 1, count( $tg['thieu'] ) );
teq( 'và gọi đúng tên nó', 'D_DA_XOA', $tg['thieu'][0] );

$r = VHDA_Tien::bo_don( $U_QL, $MA, 'D_DA_XOA' );
t( 'gỡ được đơn mồ côi', ! empty( $r['ok'] ), $r );
teq( 'gỡ xong thì hết kêu', 0, count( VHDA_Tien::tong( $ID )['thieu'] ) );

/* --- SO VỚI GIÁ TRỊ HỢP ĐỒNG (hàm thuần) --- */
$s = VHDA_Tien::so_voi_hop_dong( 0, 5000000 );
t( '🔴 giá trị hợp đồng bằng 0 thì KHÔNG so (chia cho 0)', empty( $s['co'] ), $s );
$s = VHDA_Tien::so_voi_hop_dong( 10000000, 2500000 );
teq( 'chi 2,5 triệu trên hợp đồng 10 triệu = 25%', 25, $s['phanTram'] );
t( 'và chưa vượt', empty( $s['vuot'] ) );
$s = VHDA_Tien::so_voi_hop_dong( 10000000, 12000000 );
t( '🔴 chi quá hợp đồng thì gắn cờ VƯỢT', ! empty( $s['vuot'] ), $s );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * PHẦN 7 — TRANG: VẼ RA ĐƯỢC, VÀ KHÔNG RÒ GÌ KHI CHƯA ĐĂNG NHẬP
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */

$_GET = array(); $_POST = array(); $_COOKIE = array();
ob_start(); VHDA_Trang::ve( $U_QL ); $h = ob_get_clean();
t( 'vẽ được màn danh sách', strlen( $h ) > 500, strlen( $h ) );
t( 'có tên dự án vừa lập', false !== mb_strpos( $h, 'Gian hàng GO Dĩ An' ) );

/* ---------- GIAO DIỆN ĐIỀU HÀNH: cột trái · dải thẻ số · bảng chặng ----------
   Anh Thắng 30/08/2026: *"Chuyển sang giao diện HRM trực quan"* — mở lên nhìn một cái là biết
   đang có gì, chứ không phải đọc một cái bảng rồi tự cộng trong đầu. */
t( 'có cột trái điều hướng', false !== mb_strpos( $h, 'class="trai"' ), substr( $h, 0, 800 ) );
t( 'cột trái có tên người đang đăng nhập', false !== mb_strpos( $h, 'Chị Quản Lý' ) );
t( 'có dải thẻ số', false !== mb_strpos( $h, 'class="dai"' ) );
t( '🔴 có BẢNG CHẶNG, mỗi chặng một cột', false !== mb_strpos( $h, 'class="bang"' ) );
$_so_cot = mb_substr_count( $h, 'class="cot"' );
teq( 'đúng bảy cột (cột Đã huỷ chỉ hiện khi có dự án huỷ)', 7, $_so_cot );
t( 'và dự án nằm trong đó dưới dạng thẻ', false !== mb_strpos( $h, 'class="dth"' ) );
t( 'thẻ có thanh tiến độ', false !== mb_strpos( $h, 'class="thanh"' ) );
/* Mục đang mở phải sáng lên — không thì người dùng không biết mình đang đứng ở màn nào. */
t( '🔴 mục "Bảng chặng" đang sáng khi đứng ở màn bảng',
	1 === preg_match( '#class="mi on"[^>]*>\s*<span class="ic">📊#u', $h ), 'không thấy mục sáng' );
ob_start(); VHDA_Trang::ve( $U_QL, 'ds' ); $h_ds0 = ob_get_clean();
t( '🔴 và KHÔNG còn sáng khi đã sang màn khác',
	1 !== preg_match( '#class="mi on"[^>]*>\s*<span class="ic">📊#u', $h_ds0 ), 'sáng nhầm mục' );
/* 🔴 CỘT "ĐÃ HUỶ" CHỈ HIỆN KHI CÓ dự án đã huỷ. Để nó đứng đó trống trơn quanh năm thì bảy cột
   việc thật bị bóp hẹp lại vì một cột không có gì. */
t( '🔴 không có dự án huỷ nào -> KHÔNG dựng cột Đã huỷ',
	false === mb_strpos( $h, 'class="cot huy"' ), 'dựng thừa cột huỷ' );

/* Ô lập dự án nay ở MÀN RIÊNG (cột trái → Lập dự án), không nằm chung màn bảng. */
ob_start(); VHDA_Trang::ve( $U_QL, 'lap' ); $h_lap = ob_get_clean();
t( 'Quản lý mở được màn Lập dự án', false !== mb_strpos( $h_lap, 'value="lap"' ) );
t( 'và cột trái có mục Lập dự án', false !== mb_strpos( $h, 'Lập dự án' ) );

ob_start(); VHDA_Trang::ve( $U_NV ); $h_nv = ob_get_clean();
t( '🔴 Nhân viên KHÔNG thấy mục Lập dự án ở cột trái',
	false === mb_strpos( $h_nv, 'Lập dự án' ), substr( $h_nv, 0, 1500 ) );
ob_start(); VHDA_Trang::ve( $U_NV, 'lap' ); $h_nv_lap = ob_get_clean();
t( '🔴 và gõ thẳng địa chỉ màn ấy cũng KHÔNG có ô lập (giấu nút không phải là chặn)',
	false === mb_strpos( $h_nv_lap, 'value="lap"' ), substr( $h_nv_lap, -1200 ) );
t( 'nhưng vẫn xem được dự án', false !== mb_strpos( $h_nv, 'Gian hàng GO Dĩ An' ) );

ob_start(); VHDA_Trang::ve( $U_QL, 'ds' ); $h_ds = ob_get_clean();
t( 'màn Danh sách vẫn còn cho ai quen đọc bảng',
	false !== mb_strpos( $h_ds, '<table' ) && false !== mb_strpos( $h_ds, 'Gian hàng GO Dĩ An' ) );

$_GET = array( 'da' => $MA );
ob_start(); VHDA_Trang::ve( $U_QL ); $h1 = ob_get_clean();
t( 'mở được màn một dự án', false !== mb_strpos( $h1, 'Bàn giao' ) );
t( 'có vạch chặng', false !== mb_strpos( $h1, 'class="vach"' ) );
t( 'có khối chi phí', false !== mb_strpos( $h1, 'Chi phí của dự án' ) );
t( 'có nhật ký', false !== mb_strpos( $h1, 'Nhật ký' ) );
t( 'và hai bộ phận đã bàn giao', false !== mb_strpos( $h1, 'Kỹ thuật' )
	&& false !== mb_strpos( $h1, 'Marketing' ) );

/* 🔴 Ô SỬA TIẾN ĐỘ CHỈ HIỆN Ở HÀNG CỦA BỘ PHẬN MÌNH — và hàng kia phải NÓI RÕ vì sao không
   sửa được, chứ ô xám không lời giải thích thì người ta tưởng hệ thống hỏng. */
ob_start(); VHDA_Trang::ve( $U_NV ); $h2 = ob_get_clean();
$_so_o = substr_count( $h2, 'name="viec_id"' );
teq( '🔴 bên Kỹ thuật chỉ có ĐÚNG MỘT ô sửa tiến độ', 1, $_so_o );
t( 'và hàng của bộ phận kia nói rõ vì sao không sửa được',
	false !== mb_strpos( $h2, 'còn anh/chị thuộc' ), substr( $h2, -2000 ) );

/* 🔴 MỌI BIỂU MẪU GHI ĐỀU PHẢI MANG CHỮ KÝ. Thiếu nó là trang ngoài dựng được một cái nút, người
   trong công ty bấm vào là đổi chặng dự án mà không biết. */
$_so_form = substr_count( $h1, '<form method="post"' );
$_so_ky   = substr_count( $h1, 'name="ky"' );
t( '🔴 số biểu mẫu bằng đúng số chữ ký', $_so_form === $_so_ky, array( $_so_form, $_so_ky ) );
t( 'và có ít nhất vài biểu mẫu để mà đếm', $_so_form >= 4, $_so_form );

$_GET = array();

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * PHẦN 7b — MẤY CON SỐ TRÊN DẢI THẺ. HÀM THUẦN, nên dựng được cả những cảnh hiếm.
 *
 * 🔴 Đây là thứ sếp nhìn ĐẦU TIÊN mỗi sáng. Một con số sai ở đây thì mọi quyết định sau đó đều
 *    dựa trên nó, và không ai đi kiểm lại — nên chúng phải đúng cả trong cảnh hiếm.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */

/* --- CÒN MẤY NGÀY --- */
teq( 'còn 3 ngày',            3,    VHDA_DuAn::con_may_ngay( '2026-09-04', '2026-09-01' ) );
teq( 'đúng hôm nay là 0',     0,    VHDA_DuAn::con_may_ngay( '2026-09-01', '2026-09-01' ) );
teq( 'đã qua thì ÂM',         -2,   VHDA_DuAn::con_may_ngay( '2026-08-30', '2026-09-01' ) );
/* 🔴 Chưa chốt ngày trả `null`, KHÔNG trả 0. `null` là "chưa chốt", 0 là "khai trương hôm nay" —
   hiện lẫn lộn thì dự án chưa có ngày nào nằm chung ô với dự án mở cửa sáng mai. */
teq( '🔴 ngày rỗng -> null, KHÔNG phải 0', null, VHDA_DuAn::con_may_ngay( '', '2026-09-01' ) );
teq( 'ngày sai khuôn -> null', null, VHDA_DuAn::con_may_ngay( '01/09/2026', '2026-09-01' ) );

/* --- TRỄ HẠN --- */
$_q = function ( $han, $pt, $xong = 0 ) { return array( 'han' => $han, 'phan_tram' => $pt, 'xong' => $xong ); };
t( 'quá hạn mà chưa xong: TRỄ',      VHDA_DuAn::tre_han( $_q( '2026-08-30', 40 ), '2026-09-01' ) );
t( 'chưa tới hạn: không trễ',      ! VHDA_DuAn::tre_han( $_q( '2026-09-05', 40 ), '2026-09-01' ) );
/* 🔴 XONG RỒI THÌ KHÔNG TRỄ, dù quá hạn. Tô đỏ việc đã xong chỉ làm người ta quen mắt với màu
   đỏ, rồi bỏ qua cả những cái đỏ thật. */
t( '🔴 quá hạn nhưng ĐÃ XONG: không trễ', ! VHDA_DuAn::tre_han( $_q( '2026-08-30', 100, 1 ), '2026-09-01' ) );
t( 'đạt 100% mà cờ xong chưa kịp bật: cũng không trễ',
	! VHDA_DuAn::tre_han( $_q( '2026-08-30', 100, 0 ), '2026-09-01' ) );
t( '🔴 CHƯA ĐẶT HẠN thì không trễ (không có mốc để so)',
	! VHDA_DuAn::tre_han( $_q( '', 10 ), '2026-09-01' ) );

/* --- TÓM TẮT CẢ BẢNG --- */
$_d = function ( $id, $chang, $mo_cua = '' ) {
	return array( 'id' => $id, 'chang' => $chang, 'ngay_mo_cua' => $mo_cua );
};
$_ds_t = array(
	$_d( 1, VHDA_Luong::THI_CONG, '2026-09-05' ),   // sắp mở cửa (còn 4 ngày)
	$_d( 2, VHDA_Luong::THI_CONG, '2026-10-20' ),   // còn xa
	$_d( 3, VHDA_Luong::XONG,     '2026-08-20' ),
	$_d( 4, VHDA_Luong::HUY,      '2026-09-03' ),   // đã huỷ: không đếm vào đâu cả
	$_d( 5, VHDA_Luong::MO_CUA,   '2026-09-02' ),   // ĐÃ mở cửa rồi, không còn là "sắp"
	/* 🔴 DỰ ÁN ĐANG CHẠY MÀ CHƯA GIAO CHO AI. Nó phải KHÔNG kéo tụt tiến độ trung bình xuống —
	   chưa bắt đầu thì khác hẳn "đã giao mà cả phòng ngồi chơi ở 0%". */
	$_d( 6, VHDA_Luong::PHUONG_AN, '2026-11-30' ),
);
$_v_t = array(
	1 => array( $_q( '2026-08-28', 30 ), $_q( '2026-09-10', 50 ) ),   // có một phần trễ
	2 => array( $_q( '2026-10-01', 80 ) ),
	3 => array( $_q( '2026-08-19', 100, 1 ) ),
	4 => array(),
	5 => array( $_q( '2026-09-01', 100, 1 ) ),
	6 => array(),   // chưa giao cho ai
);
$_t = VHDA_DuAn::tom_tat( $_ds_t, $_v_t, '2026-09-01' );
teq( 'tổng dự án', 6, $_t['tong'] );
teq( 'đã huỷ đếm riêng', 1, $_t['huy'] );
/* "Đang chạy" = chưa xong và chưa huỷ. Gộp cả cái đã xong vào đây thì con số ấy chỉ tăng, và
   sếp nhìn vào tưởng còn ngần ấy việc phải theo. */
teq( 'đang chạy: không tính đã huỷ, cũng KHÔNG tính đã xong', 4, $_t['dang_chay'] );
teq( 'xong', 1, $_t['xong'] );
/* 🔴 "Sắp mở cửa" chỉ đếm cái CHƯA mở. Đếm cả cái đã mở thì con số ấy chỉ tăng chứ không bao
   giờ giảm, và nó thôi có nghĩa. */
teq( '🔴 sắp mở cửa: chỉ 1 (dự án đã MỞ CỬA rồi không tính)', 1, $_t['sap_mo'] );
teq( 'đúng một dự án có bộ phận trễ hạn', 1, $_t['tre'] );
/* Tiến độ trung bình: (40 + 80 + 100 + 100) / 4 — dự án đã huỷ KHÔNG có phần việc nào nên
   không kéo tụt con số xuống. */
teq( '🔴 tiến độ trung bình BỎ QUA dự án chưa giao việc (không kéo tụt xuống)', 80, $_t['tien_do'] );

$_t0 = VHDA_DuAn::tom_tat( array(), array(), '2026-09-01' );
teq( 'bảng rỗng: tổng 0', 0, $_t0['tong'] );
teq( '🔴 và tiến độ trung bình là null, KHÔNG phải 0%', null, $_t0['tien_do'] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * PHẦN 8 — MỌI LƯỢT GHI PHẢI QUA CHỐT CHỮ KÝ
 *
 * 🔴 Thiếu nó là trang ngoài dựng được một cái nút, người trong công ty bấm vào là đổi chặng dự
 *    án — hoặc huỷ nó — mà không biết mình vừa làm gì. Đếm biểu mẫu ở phần trên chỉ chứng minh
 *    trang CÓ DỰNG ô chữ ký; phần này chứng minh máy chủ THẬT SỰ ĐÒI nó.
 *
 * ⚠️ Đi qua đúng `phuc_vu()` — cửa thật của trang. Gọi thẳng `VHDA_DuAn::chuyen()` thì không
 *    chạm tới chốt chữ ký, vì chốt ấy nằm ở tầng trang chứ không ở lõi.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */

$_TOK = VHCC_Auth::phat_token( 'Chị Quản Lý', 'Quản lý', '', 'QL001' );
$_COOKIE[ constant( 'VHCC_Web::COOKIE' ) ] = $_TOK;
t( 'dựng được phiên thật cho lượt POST', is_array( VHDA_Trang::toi() ), VHDA_Trang::toi() );

/* Đưa dự án về một chặng biết trước, rồi thử đẩy nó đi bằng POST KHÔNG chữ ký. */
VHDA_DuAn::chuyen( $U_QL, $MA, VHDA_Luong::BAN_GIAO );
teq( 'đặt lại về chặng bàn giao', VHDA_Luong::BAN_GIAO, (string) VHDA_DuAn::mot( $MA )['chang'] );

$_POST = array( 'viec' => 'chuyen', 'ma' => $MA, 'den' => VHDA_Luong::THI_CONG );
$GLOBALS['VHCP_CHUYEN'] = '';
ob_start(); VHDA_Trang::phuc_vu(); ob_get_clean();
teq( '🔴 POST KHÔNG chữ ký: dự án KHÔNG nhúc nhích', VHDA_Luong::BAN_GIAO,
	(string) VHDA_DuAn::mot( $MA )['chang'] );

$_POST['ky'] = 'chu-ky-bia-ra';
ob_start(); VHDA_Trang::phuc_vu(); ob_get_clean();
teq( '🔴 chữ ký BỊA RA cũng không đi được', VHDA_Luong::BAN_GIAO,
	(string) VHDA_DuAn::mot( $MA )['chang'] );

/* Chữ ký của KHÔNG GIAN KHÁC cũng phải bị chối — không thì biểu mẫu cắt từ trang Nội bộ dán
   sang đây là dùng được. */
$_POST['ky'] = VHCC_Phien::chu_ky( 'vhnb', $_TOK );
ob_start(); VHDA_Trang::phuc_vu(); ob_get_clean();
teq( '🔴 chữ ký của trang KHÁC cũng bị chối', VHDA_Luong::BAN_GIAO,
	(string) VHDA_DuAn::mot( $MA )['chang'] );

/* ⛔ MỘT NHÁNH KHÔNG DỰNG NỔI CẢNH Ở ĐÂY: `ky_dung()` trả `false` khi THIẾU lõi phiên. Bài này
   nạp plugin chấm công từ đầu, nên lõi luôn có mặt và phá thử "thiếu lõi phiên thì cho qua"
   luôn sống. Nhánh ấy vẫn giữ, và phải giữ đúng chiều ĐÓNG: gỡ plugin chấm công ra mà chốt chữ
   ký hoá thành "cho qua" thì mọi lượt POST vào trang này đều được nhận. Đừng đảo nó thành
   `true` chỉ vì thấy phép thử không đỏ. */
$_POST['ky'] = VHCC_Phien::chu_ky( 'vhda', $_TOK );
ob_start(); VHDA_Trang::phuc_vu(); ob_get_clean();
teq( 'chữ ký ĐÚNG thì đi được', VHDA_Luong::THI_CONG, (string) VHDA_DuAn::mot( $MA )['chang'] );
$_POST = array();
$_COOKIE = array();

VHCP_Cfg::write( VHCP_Cfg::USER, $_u_cu );

/* ================================================================= kết */

echo "\n=== KIỂM HỆ DỰ ÁN ===\n";
foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
echo 'ĐẠT: ' . $dat . '   TRƯỢT: ' . count( $truot ) . "\n";
exit( $truot ? 1 : 0 );
