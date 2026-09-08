<?php
/**
 * ADMIN ĐẶT LẠI MỐC MỘT TUẦN — "tuần này chạy từ ngày nào đến ngày nào".
 *
 * Anh Thắng 08/09/2026: *"có những tuần lỡ dở giữa tháng, nên admin có quyền chỉnh tuần đó từ
 * ngày nào đến ngày nào, để đồng bộ với các gian khác"*.
 *
 * =============================================================================================
 * 🔴 CA THẬT NẰM TRONG ẢNH ANH GỬI. Hai chuỗi cho CÙNG một tuần làm việc:
 *        T9/2026 (1/9-6/9/2026)     ← đơn lập trước khi có luật "tuần luôn đủ 7 ngày"
 *        T9/2026 (31/8-6/9/2026)    ← đơn lập sau
 *    Mọi màn coi đó là hai kỳ: lọc theo tuần ra hai dòng, đối chiếu quỹ tính hai lần, bù trừ
 *    sang tuần sau lệch theo. Gian lập sớm mang chuỗi cũ, gian lập muộn mang chuỗi mới.
 *
 * Chạy: php tools/test/kiem-doi-moc-tuan.php
 */

require_once __DIR__ . '/wp-stub.php';
$goc = dirname( dirname( __DIR__ ) );
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );
vhcp_test_dat_gio( '2026-09-08 09:00:00' );
VHCP_Auth::dat_vai_tro( 'Admin', 'Sếp' );

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
foreach ( array( 'don', 'tamung' ) as $b ) {
	$thieu = vhcp_test_bang_thieu( $b );
	t( 'bảng ' . $b . ' dựng được (không thì mọi phép dưới xanh oan)', ! $thieu, $thieu );
}

const KY_CU  = 'T9/2026 (1/9-6/9/2026)';
const KY_MOI = 'T9/2026 (31/8-6/9/2026)';

function dat_don( $ma, $ky, $tu = 0 ) {
	global $wpdb;
	$wpdb->insert( VHCP_DB::t( 'don' ), array( 'ma_don' => $ma, 'ky' => $ky, 'trang_thai' => 'Chờ quyết toán', 'nguoi_lap' => 'NV' ) );
	if ( $tu ) { $wpdb->insert( VHCP_DB::t( 'tamung' ), array( 'ma_don' => $ma, 'coso' => 'CS', 'so' => $tu ) ); }
}
function ky_cua( $ma ) {
	global $wpdb;
	$t = VHCP_DB::t( 'don' );
	return (string) $wpdb->get_var( $wpdb->prepare( "SELECT ky FROM $t WHERE ma_don=%s", $ma ) );
}
function so_tu_ung( $ma ) {
	global $wpdb;
	$t = VHCP_DB::t( 'tamung' );
	return (float) $wpdb->get_var( $wpdb->prepare( "SELECT so FROM $t WHERE ma_don=%s", $ma ) );
}

dat_don( 'D_CU1', KY_CU, 500000 );
dat_don( 'D_CU2', KY_CU );
dat_don( 'D_MOI', KY_MOI );
dat_don( 'D_KHAC', 'T8/2026 (24/8-30/8/2026)' );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 1. DANH SÁCH KỲ ĐỌC TỪ CHÍNH BẢNG ĐƠN
 *
 * 🔴 Kỳ cần sửa đúng là kỳ SAI khuôn. Dựng danh sách từ lịch thì không bao giờ bày ra được
 *    nó, và admin không có gì để chọn.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$ds = VHCP_Don::ds_ky_dang_co();
$ky_ten = array();
foreach ( $ds['items'] as $x ) { $ky_ten[ $x['ky'] ] = $x; }
t( '🔴 kỳ SAI khuôn vẫn được bày ra', isset( $ky_ten[ KY_CU ] ), array_keys( $ky_ten ) );
t( 'kỳ đúng khuôn cũng có',          isset( $ky_ten[ KY_MOI ] ), array_keys( $ky_ten ) );
teq( 'đếm đúng số đơn mỗi kỳ', 2, $ky_ten[ KY_CU ]['soDon'] );
teq( 'và bóc ra được khoảng ngày để điền sẵn hai ô', '2026-09-01', $ky_ten[ KY_CU ]['tu'] );
teq( 'ngày cuối cũng bóc đúng',                      '2026-09-06', $ky_ten[ KY_CU ]['den'] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 2. ĐỔI MỐC — VÀ GỘP, vì đó chính là thứ anh Thắng cần
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$r = VHCP_Don::doi_moc_ky( KY_CU, '31/08/2026', '06/09/2026' );
t( 'đổi được', ! empty( $r['success'] ), $r );
teq( '🔴 chuỗi mới đúng bằng chuỗi tuần chuẩn', KY_MOI, $r['kyMoi'] );
teq( 'đổi đúng 2 đơn',                          2,      $r['doi'] );
teq( '🔴 và BÁO là đã gộp vào kỳ có sẵn',       true,   $r['gop'] );

teq( '🔴 đơn cũ nay mang kỳ mới · D_CU1', KY_MOI, ky_cua( 'D_CU1' ) );
teq( 'đơn cũ nay mang kỳ mới · D_CU2',    KY_MOI, ky_cua( 'D_CU2' ) );
teq( 'đơn vốn đã đúng thì giữ nguyên',    KY_MOI, ky_cua( 'D_MOI' ) );
teq( '⚠️ kỳ KHÁC không bị đụng tới', 'T8/2026 (24/8-30/8/2026)', ky_cua( 'D_KHAC' ) );

/* 🔴 HAI BẢNG CON KHÔNG CÓ CỘT KỲ — và đó là lý do KHÔNG được đụng vào chúng.
   `tamung` khoá theo (ma_don, coso), `chiphi` khoá theo ma_don; kỳ của chúng suy từ đơn cha
   nên tự đúng theo. Bản nháp có thêm một câu `UPDATE tamung SET ky=...` "cho chắc" — cột ấy
   không tồn tại, câu lệnh sẽ nổ trên host thật, và phép dưới bắt được ngay lượt chạy đầu. */
$cot_tu = array();
foreach ( VHCP_DB::rows( 'SELECT * FROM ' . VHCP_DB::t( 'tamung' ) . ' LIMIT 1' ) as $r0 ) { $cot_tu = array_keys( $r0 ); }
t( '🔴 bảng tạm ứng KHÔNG có cột kỳ', ! in_array( 'ky', $cot_tu, true ), $cot_tu );
teq( 'và số tạm ứng còn nguyên sau lượt đổi kỳ', 500000.0, so_tu_ung( 'D_CU1' ) );
teq( 'đơn của nó cũng đã sang kỳ mới',           KY_MOI,   ky_cua( 'D_CU1' ) );

/* Gộp xong thì danh sách chỉ còn một kỳ cho tuần ấy, mang đủ 3 đơn. */
$ds2 = VHCP_Don::ds_ky_dang_co();
$m2  = array();
foreach ( $ds2['items'] as $x ) { $m2[ $x['ky'] ] = $x['soDon']; }
t( '🔴 kỳ cũ biến mất khỏi danh sách', ! isset( $m2[ KY_CU ] ), array_keys( $m2 ) );
teq( 'và kỳ mới gom đủ 3 đơn', 3, $m2[ KY_MOI ] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 3. NHÃN THÁNG LẤY THEO NGÀY CUỐI — không phải sở thích, là bắt buộc
 *
 * `khoang_ky()` và `ky_num()` đọc con số trong nhãn NHƯ LÀ tháng của ngày cuối, rồi suy ngược
 * năm bằng `sm > em ? yy-1 : yy`. Lấy tháng của ngày ĐẦU thì tuần bắc tháng sinh ra một nhãn
 * mà chính hệ này đọc lại thành năm khác.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
dat_don( 'D_BAC', 'T12/2026 (28/12-31/12/2026)' );
$rb = VHCP_Don::doi_moc_ky( 'T12/2026 (28/12-31/12/2026)', '28/12/2026', '03/01/2027' );
teq( '🔴 tuần bắc sang năm mới lấy nhãn theo ngày CUỐI', 'T1/2027 (28/12-3/1/2027)', $rb['kyMoi'] );
list( $a, $b ) = VHCP_Don::khoang_ky( $rb['kyMoi'] );
teq( '🔴 và chính hệ này đọc ngược lại ra đúng ngày đầu', '2026-12-28', $a );
teq( 'đọc ngược ra đúng ngày cuối',                       '2027-01-03', $b );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 4. CHỐT CHẶN — đổi hàng loạt thì phải khó gõ nhầm
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$e1 = VHCP_Don::doi_moc_ky( KY_MOI, '06/09/2026', '31/08/2026' );
t( '🔴 ngày đầu sau ngày cuối thì chối', empty( $e1['success'] ), $e1 );
$e2 = VHCP_Don::doi_moc_ky( KY_MOI, '01/01/2026', '31/12/2026' );
t( '🔴 khoảng dài hơn 31 ngày thì chối (gõ nhầm năm)', empty( $e2['success'] ), $e2 );
/* ⚠️ NGÀY THỬ PHẢI GẦN NHAU. "31/02/2026 → 06/09/2026" cũng bị chối, nhưng bị chối bởi chốt
   31-ngày ở trên chứ không phải bởi `checkdate()` — nên phép này xanh kể cả khi bỏ hẳn
   `checkdate`. Phá thử chỉ ra đúng chỗ ấy. Lấy khoảng ngắn để chỉ còn một chốt chịu trách
   nhiệm. */
$e3 = VHCP_Don::doi_moc_ky( KY_MOI, '31/02/2026', '05/03/2026' );
t( '🔴 ngày không có thật (31/02) thì chối', empty( $e3['success'] ), $e3 );
$e4 = VHCP_Don::doi_moc_ky( 'T9/2026 (KHÔNG CÓ THẬT)', '31/08/2026', '06/09/2026' );
t( 'kỳ không có đơn nào thì chối', empty( $e4['success'] ), $e4 );
$e5 = VHCP_Don::doi_moc_ky( '', '31/08/2026', '06/09/2026' );
t( 'kỳ rỗng thì chối', empty( $e5['success'] ), $e5 );
/* 🔴 Đặt lại đúng mốc đang có = không có gì để đổi. Cho chạy thì nhật ký đầy những dòng "đã
   đổi 0 đơn", và người đọc sổ sau này không biết dòng nào là lượt đổi thật. */
$e6 = VHCP_Don::doi_moc_ky( KY_MOI, '31/08/2026', '06/09/2026' );
t( '🔴 mốc mới trùng mốc cũ thì chối, không ghi vết thừa', empty( $e6['success'] ), $e6 );

/* Nhận cả 'Y-m-d' — ô <input type="date"> của trình duyệt gửi lên đúng khuôn ấy. */
dat_don( 'D_ISO', 'T7/2026 (6/7-12/7/2026)' );
$r7 = VHCP_Don::doi_moc_ky( 'T7/2026 (6/7-12/7/2026)', '2026-07-06', '2026-07-11' );
t( '🔴 nhận khuôn Y-m-d của ô chọn ngày', ! empty( $r7['success'] ), $r7 );
teq( 'và dựng đúng chuỗi', 'T7/2026 (6/7-11/7/2026)', $r7['kyMoi'] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 5. CHỈ ADMIN — đổi hàng loạt đơn và gộp được hai kỳ thì không mở cho kế toán
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$api = file_get_contents( dirname( dirname( __DIR__ ) ) . '/wordpress/vhcp-chi-phi/includes/class-vhcp-api.php' );
/* ⚠️ BÓC NGUYÊN KHỐI `$admin_only = array( … );` RỒI SOI TRONG ĐÓ, đừng để biểu thức chạy lan.
   `[\s\S]*?` không giới hạn sẽ khớp qua cả những mảng khác phía dưới, nên tên nằm ở nhóm
   "cấu hình" cũng làm phép này xanh — tức phép canh quyền mà không canh được gì. */
$ao = '';
if ( preg_match( '/\$admin_only = array\((.*?)\n\t\t\);/s', $api, $m_ao ) ) { $ao = $m_ao[1]; }
t( '🔴 bóc được khối $admin_only', '' !== $ao );
t( '🔴 doiMocKy nằm trong nhóm chỉ-Admin', false !== strpos( $ao, "'doiMocKy'" ), $ao );
/* Đối chứng hai chiều: `dsKyDangCo` chỉ ĐỌC danh sách kỳ nên kế toán vào được — nó KHÔNG được
   nằm trong khối chỉ-Admin. Không có phép này thì một biểu thức bóc nhầm cả tệp vẫn xanh. */
t( '⚠️ đối chứng: dsKyDangCo KHÔNG nằm trong nhóm chỉ-Admin', false === strpos( $ao, "'dsKyDangCo'" ), $ao );
t( 'và có khai vào bảng hàm', false !== strpos( $api, "'doiMocKy'              => array( 'VHCP_Don', 'doi_moc_ky' )" ) );
t( 'dsKyDangCo cũng khai',    false !== strpos( $api, "'dsKyDangCo'            => array( 'VHCP_Don', 'ds_ky_dang_co' )" ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 6. GHI VẾT — đụng hàng loạt đơn thì sổ phải nhớ
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$t_log = VHCP_DB::t( 'log' );
$vet   = (string) $wpdb->get_var( "SELECT chi_tiet FROM $t_log WHERE hanh_dong='Đổi mốc tuần' ORDER BY id DESC LIMIT 1" );
t( '🔴 có ghi vết lượt đổi', '' !== $vet, $vet );
t( 'vết nói rõ từ kỳ nào sang kỳ nào', false !== strpos( $vet, '->' ), $vet );

/* ═══════════════════════════════════════════════════════════════════════════════════════════ */
if ( $truot ) {
	echo "\n✗ TRƯỢT " . count( $truot ) . " phép (đạt $dat):\n";
	foreach ( $truot as $x ) { echo "  · $x\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $dat phép: admin đặt lại được mốc tuần, và hai chuỗi lệch của cùng một tuần gộp làm một.\n";
