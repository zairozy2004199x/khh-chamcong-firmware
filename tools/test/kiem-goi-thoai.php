<?php
/**
 * KIỂM GỌI THOẠI TRONG APP — phần MÁY CHỦ.
 *
 * ═════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 BÀI NÀY KHÔNG THỬ ĐƯỢC TIẾNG NÓI, VÀ KHÔNG GIẢ VỜ LÀ CÓ.
 * ═════════════════════════════════════════════════════════════════════════════════════════════
 * Âm thanh đi thẳng giữa hai máy bằng WebRTC; muốn thử thật phải có hai trình duyệt thật và một
 * máy TURN thật. Cái thử được ở đây là phần máy chủ làm — và đó cũng là phần chứa mọi chỗ hỏng
 * nguy hiểm:
 *
 *   · BÍ MẬT TURN có rò xuống máy người dùng không? (rò là cả thế giới mượn được máy TURN)
 *   · Người THỨ BA có đọc được mai mối của cuộc gọi người khác không? (mai mối đủ để nối vào)
 *   · Người GỌI có tự "nghe máy" hộ người nhận được không? (micro bên kia mở mà chủ không biết)
 *   · Cuộc gọi bỏ dở có tự chết không? (không thì sáng mai đổ chuông cuộc của đêm qua)
 *
 * Chạy: php tools/test/kiem-goi-thoai.php
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

$CS = 'GOI_SHOP';
$CS2 = 'GOI_SHOP_2';
$nv = function ( $ma, $ten, $cs, $pin ) use ( $wpdb ) {
	$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array(
		'ma_nv' => $ma, 'ho_ten' => $ten, 'cua_hang' => $cs, 'vai_tro' => 'Nhân viên',
		'pin_dang_nhap' => $pin, 'trang_thai_lam_viec' => 'Đang làm' ) );
};
$nv( 'G_A', 'Người Gọi', $CS, '920001' );
$nv( 'G_B', 'Người Nhận', $CS, '920002' );
$nv( 'G_C', 'Người Thứ Ba', $CS, '920003' );
$nv( 'G_X', 'Người Cơ Sở Khác', $CS2, '920004' );

$phien = function ( $pin ) { return VHCC_Tram::nguoi( VHCC_Tram::dang_nhap( $pin )['token'] ); };
$A = $phien( '920001' ); $B = $phien( '920002' );
$C = $phien( '920003' ); $X = $phien( '920004' );
t( 'bốn người đăng nhập được', $A && $B && $C && $X );

/* ═══════════════════════════════════ 1. CHƯA DỰNG TURN -> NÓI THẲNG, KHÔNG THỬ RỒI HỎNG */

/* 🔴 Không có TURN thì cuộc gọi vẫn "bấm được", vẫn đổ chuông, rồi im lặng và tự tắt — hỏng
   đúng kiểu tệ nhất: trông như máy yếu chứ không ai nghĩ là thiếu hạ tầng. */
t( 'chưa khai TURN -> chưa sẵn sàng', ! VHCC_Goi::san_sang() );
$v = VHCC_Goi::ve( $A );
t( '🔴 xin vé khi chưa có TURN -> chối, không trả vé rỗng', empty( $v['ok'] ), $v );
t( '   và nói rõ phải khai gì ở đâu',
	! empty( $v['error'] ) && false !== mb_strpos( $v['error'], 'wp-config' ), $v );
$r = VHCC_Goi::goi( $A, 'G_B' );
t( '🔴 gọi khi chưa có TURN -> chối ngay, không tạo cuộc treo', empty( $r['ok'] ), $r );
t( '   và KHÔNG có hàng nào trong sổ cuộc gọi',
	0 === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'cuoc_goi' ) ) );

/* Khai TURN vào option (trên máy thật thì khai ở wp-config.php). */
$BI_MAT = 'bi-mat-thu-nghiem-khong-dung-that';
update_option( 'vhcc_turn_may', 'turn.thu.test:3478' );
update_option( 'vhcc_turn_bi_mat', $BI_MAT );
t( 'khai xong -> sẵn sàng', VHCC_Goi::san_sang() );

/* ═════════════════════════════════════════════ 2. VÉ TURN: TẠM, VÀ KHÔNG RÒ BÍ MẬT */

$v = VHCC_Goi::ve( $A );
t( 'phát được vé', ! empty( $v['ok'] ), $v );
$json = wp_json_encode( $v );
/* 🔴 CHỖ NGUY HIỂM NHẤT CỦA CẢ TÍNH NĂNG. Gửi chuỗi bí mật xuống trình duyệt là ai mở màn gọi
   cũng có nó, và nó dùng được MÃI — cả thế giới mượn được máy TURN của mình làm nơi chuyển
   tiếp, mình trả băng thông. */
t( '🔴 vé KHÔNG chứa chuỗi bí mật', false === strpos( $json, $BI_MAT ), $json );

$tk = '';
foreach ( $v['may'] as $m ) { if ( isset( $m['username'] ) ) { $tk = (string) $m['username']; } }
t( 'vé có tên đăng nhập tạm', '' !== $tk, $v );
t( '🔴 tên vé là <hết hạn>:<mã NV>', 1 === preg_match( '/^\d{10,}:G_A$/', $tk ), $tk );
$het = (int) explode( ':', $tk )[0];
t( 'vé hết hạn trong tương lai', $het > time(), $het . ' vs ' . time() );
t( '🔴 vé KHÔNG sống quá ' . VHCC_Goi::VE_SONG . 's', $het - time() <= VHCC_Goi::VE_SONG + 5 );

/* Mật khẩu phải đúng công thức của coturn `use-auth-secret`, nếu không coturn chối mọi vé. */
$mk = '';
foreach ( $v['may'] as $m ) { if ( isset( $m['credential'] ) ) { $mk = (string) $m['credential']; } }
t( '🔴 mật khẩu vé = base64(HMAC-SHA1(tên, bí mật)) đúng kiểu coturn',
	$mk === base64_encode( hash_hmac( 'sha1', $tk, $BI_MAT, true ) ), $mk );

/* Hai người khác nhau -> hai vé khác nhau. Dùng chung một vé thì không truy được ai xài. */
$v_b = VHCC_Goi::ve( $B );
$tk_b = '';
foreach ( $v_b['may'] as $m ) { if ( isset( $m['username'] ) ) { $tk_b = (string) $m['username']; } }
t( '⚠️ mỗi người một vé riêng', $tk !== $tk_b, array( $tk, $tk_b ) );

/* 🔴 GIỮ CẢ STUN. Bỏ đi thì hai máy cùng wifi cửa hàng cũng chạy vòng qua TURN — tốn băng thông
   và thêm độ trễ cho một việc chúng tự làm được. */
t( '🔴 vé có CẢ stun lẫn turn',
	false !== strpos( $json, 'stun:' ) && false !== strpos( $json, 'turn:' ), $json );

/* ══════════════════════════════════════════════ 3. AI GỌI ĐƯỢC AI */

$r = VHCC_Goi::goi( $A, 'G_B' );
t( 'gọi được người cùng cơ sở', ! empty( $r['ok'] ), $r );
$id = (int) $r['id'];

/* 🔴 Dùng lại đúng phép gác của chat riêng — hai người phải cùng một cơ sở. */
t( '🔴 KHÔNG gọi được người cơ sở khác', empty( VHCC_Goi::goi( $A, 'G_X' )['ok'] ) );
t( '🔴 KHÔNG tự gọi chính mình', empty( VHCC_Goi::goi( $A, 'G_A' )['ok'] ) );
t( 'mã không có thật -> chối', empty( VHCC_Goi::goi( $A, 'KHONG_CO' )['ok'] ) );

/* 🔴 MỘT NGƯỜI MỘT CUỘC. Hai người cùng gọi một người thứ ba thì máy kia đổ hai chuông, nghe
   một cuộc, còn cuộc kia treo mãi ở "đang đổ chuông" — đầu bên kia ngồi nghe chuông giả. */
t( '🔴 đang có cuộc thì người thứ ba KHÔNG gọi chen vào được',
	empty( VHCC_Goi::goi( $C, 'G_B' )['ok'] ), VHCC_Goi::goi( $C, 'G_B' ) );

/* ══════════════════════════════════════════════ 4. ĐỔ CHUÔNG VÀ TRẢ LỜI */

$c = VHCC_Goi::cho( $B );
t( 'B thấy cuộc gọi đang đổ chuông', $c && (int) $c['id'] === $id, $c );
t( '   và biết ai gọi', $c && 'Người Gọi' === $c['tenGoi'], $c );
t( '🔴 người thứ ba KHÔNG thấy cuộc gọi ấy', null === VHCC_Goi::cho( $C ) );
t( '🔴 chính người gọi cũng không thấy nó trong hộp "ai gọi tôi"', null === VHCC_Goi::cho( $A ) );

/* ⚠️ CHỈ NGƯỜI NHẬN trả lời được. Người gọi tự "nghe máy" hộ là nối máy với một người chưa bấm
   gì — micro bên kia mở ra mà chủ nhân không biết. */
t( '🔴 người GỌI không tự nghe máy hộ được',
	empty( VHCC_Goi::tra_loi( $A, $id, true )['ok'] ), VHCC_Goi::tra_loi( $A, $id, true ) );
t( '🔴 người thứ ba không trả lời được', empty( VHCC_Goi::tra_loi( $C, $id, true )['ok'] ) );

$r = VHCC_Goi::tra_loi( $B, $id, true );
t( 'người nhận nghe máy được', ! empty( $r['ok'] ) && 'nghe' === $r['trangThai'], $r );
t( 'hai bên cùng thấy trạng thái "nghe"',
	'nghe' === VHCC_Goi::trang_thai( $A, $id )['trangThai'], VHCC_Goi::trang_thai( $A, $id ) );
t( 'trả lời lần hai bị chối', empty( VHCC_Goi::tra_loi( $B, $id, true )['ok'] ) );

/* ═════════════════════════════════════════ 5. MAI MỐI: CHỈ HAI NGƯỜI TRONG CUỘC */

t( 'A gửi được offer', ! empty( VHCC_Goi::gui_tin( $A, $id, 'offer', 'v=0 SDP giả' )['ok'] ) );
t( 'B gửi được answer', ! empty( VHCC_Goi::gui_tin( $B, $id, 'answer', 'v=0 SDP giả 2' )['ok'] ) );
t( 'gửi được ice', ! empty( VHCC_Goi::gui_tin( $A, $id, 'ice', 'candidate:1 ...' )['ok'] ) );
t( 'loại tin lạ bị chối', empty( VHCC_Goi::gui_tin( $A, $id, 'hack', 'x' )['ok'] ) );
t( 'mẩu tin quá lớn bị chối',
	empty( VHCC_Goi::gui_tin( $A, $id, 'ice', str_repeat( 'x', VHCC_Goi::TIN_TOI_DA + 1 ) )['ok'] ) );

/* 🔴 NGƯỜI THỨ BA KHÔNG ĐỌC ĐƯỢC MAI MỐI. Mai mối đủ để nối vào cuộc gọi ấy. */
$d = VHCC_Goi::doc_tin( $C, $id, 0 );
t( '🔴 người thứ ba KHÔNG đọc được mai mối', empty( $d['ok'] ), $d );
t( '🔴 người thứ ba KHÔNG gửi được mai mối vào cuộc người khác',
	empty( VHCC_Goi::gui_tin( $C, $id, 'ice', 'chen vao' )['ok'] ) );
t( '🔴 người thứ ba KHÔNG cúp máy hộ được', empty( VHCC_Goi::ket( $C, $id )['ok'] ) );

/* 🔴 CHỈ LẤY TIN CỦA BÊN KIA. Lấy cả tin của mình thì trình duyệt nạp lại chính lời mời của nó
   vào `setRemoteDescription` — WebRTC ném lỗi trạng thái và cuộc gọi chết ở nhịp đầu. */
$d = VHCC_Goi::doc_tin( $B, $id, 0 );
t( 'B đọc được mai mối của A', ! empty( $d['ok'] ) && count( $d['ds'] ) >= 2, $d );
$co_cua_b = false;
foreach ( $d['ds'] as $x ) { if ( 'answer' === $x['loai'] ) { $co_cua_b = true; } }
t( '🔴 và KHÔNG lấy lại tin của chính B', ! $co_cua_b, $d );

/* Đọc tiếp từ id cuối -> không lấy lại cái đã lấy. */
$cuoi = 0;
foreach ( $d['ds'] as $x ) { if ( $x['id'] > $cuoi ) { $cuoi = $x['id']; } }
t( 'đọc tiếp từ id cuối -> rỗng', 0 === count( VHCC_Goi::doc_tin( $B, $id, $cuoi )['ds'] ) );

/* ══════════════════════════════════════════════════ 6. CÚP MÁY VÀ HẾT HẠN */

t( 'B cúp máy được', ! empty( VHCC_Goi::ket( $B, $id )['ok'] ) );
t( 'A thấy cuộc đã xong', 'xong' === VHCC_Goi::trang_thai( $A, $id )['trangThai'] );
t( 'cuộc đã xong thì không gửi mai mối được nữa',
	empty( VHCC_Goi::gui_tin( $A, $id, 'ice', 'muon' )['ok'] ) );

/* 🔴 CUỘC BỎ DỞ PHẢI TỰ CHẾT. Không thì một máy tắt nguồn lúc 9 giờ tối, sáng mai mở lên là đổ
   chuông một cuộc của đêm qua — và người nhận bấm nghe vào một cuộc không còn ai ở đầu kia. */
$r = VHCC_Goi::goi( $A, 'G_B' );
$id2 = (int) $r['id'];
$wpdb->update( VHCC_DB::t( 'cuoc_goi' ),
	array( 'tao_luc' => gmdate( 'Y-m-d H:i:s',
		strtotime( current_time( 'mysql' ) ) - VHCC_Goi::CHUONG_GIAY - 10 ) ),
	array( 'id' => $id2 ) );
t( '🔴 cuộc đổ chuông quá hạn -> B KHÔNG còn thấy', null === VHCC_Goi::cho( $B ) );
$tt = VHCC_Goi::trang_thai( $A, $id2 );
t( '   và nó được đánh dấu NHỠ', 'xong' === $tt['trangThai'] && 'nho' === $tt['lyDo'], $tt );

/* Hết cuộc treo thì gọi lại được. */
t( 'sau khi hết hạn thì gọi lại được', ! empty( VHCC_Goi::goi( $A, 'G_B' )['ok'] ) );

/* ═════════════════════════════════════ 7. BÍ MẬT KHÔNG LỌT RA KHỎI LỚP */

/* 🔴 CANH THẲNG TRONG MÃ NGUỒN. Phép thử ở khối 2 chỉ chứng minh HÔM NAY vé không chứa bí mật;
   chốt này chặn cái ngày có người thêm một cửa "trả cấu hình TURN cho client" cho tiện. */
$src = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-goi.php' );
t( '🔴 hàm đọc bí mật là private', false !== strpos( $src, 'private static function turn_bi_mat' ) );
$n_dung = substr_count( $src, 'turn_bi_mat()' );
t( '🔴 bí mật chỉ được dùng ở HAI chỗ (san_sang + ve)', $n_dung <= 3, $n_dung . ' lần' );

$src_t = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-tram.php' );
t( '🔴 cổng trạm KHÔNG đụng tới bí mật TURN',
	false === strpos( $src_t, 'turn_bi_mat' ) && false === strpos( $src_t, 'VHCC_TURN_BI_MAT' ) );
foreach ( array( 'goi_ve', 'goi_moi', 'goi_cho', 'goi_tra_loi', 'goi_ket', 'goi_gui', 'goi_doc' ) as $cua ) {
	t( 'cổng có cửa `' . $cua . '`', false !== strpos( $src_t, "'" . $cua . "' === \$viec" ) );
}

echo "\n";
if ( $truot ) {
	echo 'TRƯỢT ' . count( $truot ) . ":\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — bí mật TURN không rời máy chủ, và người thứ ba không chạm được vào cuộc gọi.\n";
