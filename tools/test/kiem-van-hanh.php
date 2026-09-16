<?php
/**
 * KIỂM PLUGIN VẬN HÀNH (wordpress/vhcp-van-hanh).
 *
 * =============================================================================================
 * 🔴 BÀI NÀY CANH HAI THỨ BẢN FIREBASE LÀM SAI
 * =============================================================================================
 * 1. **QUYỀN Ở MÁY CHỦ.** Bản cũ đọc danh tính thẳng từ `localStorage` và so mật khẩu trong
 *    trình duyệt — sửa một biến là thành Quản lý. Ở đây mọi đường đều phải chối khi thiếu thẻ,
 *    và chối cả khi có thẻ thật nhưng sai vai hoặc sai cơ sở.
 * 2. **TIỀN DO MÁY CHỦ CỘNG.** Bản cũ cộng ở máy khách rồi ghi thẳng con số ấy. Ở đây trang chỉ
 *    gửi SỐ LƯỢNG vé; gửi kèm `tong_thu` giả phải không có tác dụng gì.
 *
 * Chạy: php tools/test/kiem-van-hanh.php
 */

require_once __DIR__ . '/wp-stub.php';

if ( ! function_exists( 'register_activation_hook' ) ) {
	function register_activation_hook( $tep, $cb ) { return true; }
}
if ( ! function_exists( 'register_rest_route' ) ) {
	function register_rest_route( $ns, $duong, $tuy = array() ) { return true; }
}
if ( ! function_exists( 'add_rewrite_rule' ) ) {
	function add_rewrite_rule( $a, $b, $c = 'bottom' ) { return true; }
}
if ( ! function_exists( 'flush_rewrite_rules' ) ) {
	function flush_rewrite_rules( $x = true ) { return true; }
}
if ( ! function_exists( 'nocache_headers' ) ) {
	function nocache_headers() { return true; }
}
if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $v ) { return trim( strip_tags( (string) $v ) ); }
}

/* ------------------------------------------------------------------ đếm & in kết quả */
$DAT = 0; $TRUOT = array();
function t( $ten, $dung, $them = null ) {
	global $DAT, $TRUOT;
	if ( $dung ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null === $them ? '' : ' — ' . ( is_scalar( $them ) ? $them : wp_json_encode( $them ) ) );
}

/* ------------------------------------------------------------------ nạp plugin
   🔴 Bảng dựng từ CHÍNH `VHVH_DB::bang()` qua `vhvh_test_boot()`, không gõ tay lại ở đây.
      `dbDelta()` trong bệ đỡ là hàm RỖNG, nên gọi `VHVH_DB::cai_dat()` không dựng được bảng
      nào cả — mà `$wpdb->insert()` vào bảng không có thì trả về false, không ném gì, và bài
      kiểm chỉ thấy "sổ rỗng", trông y hệt lỗi của plugin. */
vhvh_test_boot( dirname( __DIR__, 2 ) . '/wordpress/vhcp-van-hanh' );

/* Bốn người, bốn vai. `coso` để đúng như thẻ phiên của chấm công trả về. */
$NV  = array( 'ten' => 'Nhân Viên A', 'ma_nv' => 'NV01', 'coso' => 'GO BÀ RỊA', 'vai' => 'nhan_vien' );
$TN  = array( 'ten' => 'Thu Ngân B',  'ma_nv' => 'NV02', 'coso' => 'GO BÀ RỊA', 'vai' => 'thu_ngan' );
$CHT = array( 'ten' => 'CHT C',       'ma_nv' => 'NV03', 'coso' => 'GO BÀ RỊA', 'vai' => 'cua_hang_truong' );
$QL  = array( 'ten' => 'Quản Lý D',   'ma_nv' => 'NV04', 'coso' => '',          'vai' => 'quan_ly' );

/* =============================================================================================
 * 1. ĐỔI VAI TRÒ TỪ HỆ CHẤM CÔNG
 * =========================================================================================== */
t( 'Admin → quan_ly',            'quan_ly'         === VHVH_Auth::doi_vai( 'Admin' ) );
t( 'Quản lý → quan_ly',          'quan_ly'         === VHVH_Auth::doi_vai( 'Quản lý' ) );
t( 'Cửa hàng trưởng → cht',      'cua_hang_truong' === VHVH_Auth::doi_vai( 'Cửa hàng trưởng' ) );
t( 'Kế toán cá nhân → thu_ngan', 'thu_ngan'        === VHVH_Auth::doi_vai( 'Kế toán cá nhân' ) );
t( 'Nhân viên → nhan_vien',      'nhan_vien'       === VHVH_Auth::doi_vai( 'Nhân viên' ) );

/* 🔴 VAI LẠ PHẢI RƠI XUỐNG QUYỀN THẤP NHẤT. Ai đó gõ sai chính tả một vai trò bên chấm công mà
   ở đây lại rơi vào nhánh quyền cao là cả doanh thu mở toang, và không có câu báo nào. */
t( '🔴 vai lạ → nhan_vien, KHÔNG phải quan_ly', 'nhan_vien' === VHVH_Auth::doi_vai( 'Quản Lý' ) );
t( '🔴 vai rỗng → nhan_vien',                   'nhan_vien' === VHVH_Auth::doi_vai( '' ) );
t( '🔴 vai null → nhan_vien',                   'nhan_vien' === VHVH_Auth::doi_vai( null ) );

/* =============================================================================================
 * 2. BẬC QUYỀN
 * =========================================================================================== */
t( 'quản lý đủ quyền cửa hàng trưởng', VHVH_Auth::du_quyen( $QL, 'cua_hang_truong' ) );
t( 'quản lý đủ quyền thu ngân',        VHVH_Auth::du_quyen( $QL, 'thu_ngan' ) );
t( 'CHT đủ quyền thu ngân',            VHVH_Auth::du_quyen( $CHT, 'thu_ngan' ) );
t( '🔴 thu ngân KHÔNG đủ quyền CHT',   ! VHVH_Auth::du_quyen( $TN, 'cua_hang_truong' ) );
t( '🔴 nhân viên KHÔNG đủ quyền thu ngân', ! VHVH_Auth::du_quyen( $NV, 'thu_ngan' ) );
t( 'người rỗng thì chối',              ! VHVH_Auth::du_quyen( null, 'nhan_vien' ) );
t( 'bậc không có thật thì chối',       ! VHVH_Auth::du_quyen( $QL, 'ong_troi' ) );

/* 🔴 CHỈ QUẢN LÝ XEM MỌI CƠ SỞ. Cửa hàng trưởng cơ sở A mở được sổ tiền cơ sở B là chuyện không
   ai muốn giải thích — và nếu chặn nằm ở trình duyệt thì gọi thẳng API là qua. */
t( 'quản lý xem mọi cơ sở', true === VHVH_Auth::coso_duoc( $QL ) );
t( '🔴 CHT chỉ xem cơ sở của mình', array( 'GO BÀ RỊA' ) === VHVH_Auth::coso_duoc( $CHT ) );
t( '🔴 CHT KHÔNG đụng được cơ sở khác', ! VHVH_Auth::duoc_coso( $CHT, 'VŨNG TÀU' ) );
t( 'CHT đụng được cơ sở của mình', VHVH_Auth::duoc_coso( $CHT, 'GO BÀ RỊA' ) );
t( 'quản lý đụng được cơ sở bất kỳ', VHVH_Auth::duoc_coso( $QL, 'VŨNG TÀU' ) );

/* =============================================================================================
 * 3. TÍNH TIỀN — MÁY CHỦ CỘNG, KHÔNG TIN TRANG
 * =========================================================================================== */
$r = VHVH_Tien::tinh( array( 've_1luot' => 10, 'combo_ve_bua' => 2, 'bua_le' => 5 ) );
t( 'cộng đúng doanh thu', 10 * 100000 + 2 * 120000 + 5 * 30000 === $r['tong_thu'], $r['tong_thu'] );
/* Bùa lẻ là món bán thêm cho người đã vào — đếm là khách nữa thì số khách nhân đôi. */
t( '🔴 bùa lẻ KHÔNG tính là lượt khách', 12 === $r['khach'], $r['khach'] );

/* Vé online: tiền khách trả cho sàn chứ không vào két cơ sở. */
$r2 = VHVH_Tien::tinh( array( 'online_1luot' => 8 ) );
t( '🔴 vé online KHÔNG vào doanh thu cơ sở', 0 === $r2['tong_thu'], $r2['tong_thu'] );
t( 'nhưng VẪN tính là lượt khách', 8 === $r2['khach'] );

$r3 = VHVH_Tien::tinh( array( 've_1luot' => -5 ) );
t( '🔴 số vé âm bị kẹp về 0', 0 === $r3['tong_thu'] && 0 === $r3['khach'] );

$r4 = VHVH_Tien::tinh( array( 'khong_co_loai_nay' => 99 ) );
t( 'loại vé lạ bị bỏ qua, không nổ', 0 === $r4['tong_thu'] && ! isset( $r4['ve']['khong_co_loai_nay'] ) );

$r5 = VHVH_Tien::tinh( array(), array( array( 'nhan' => 'Nước', 'tien' => 50000 ),
	array( 'nhan' => '', 'tien' => 0 ) ), array( array( 'nhan' => 'Bán áo', 'tien' => 200000 ) ) );
t( 'cộng chi phí', 50000 === $r5['tong_chi'] );
t( 'dòng trống bị bỏ', 1 === count( $r5['chi'] ) );
t( 'thu khác cộng vào doanh thu', 200000 === $r5['tong_thu'] );

/* =============================================================================================
 * 4. GHI BÁO CÁO NGÀY
 * =========================================================================================== */
$hnay = current_time( 'Y-m-d' );
$goi_tien = array( 'coso' => 'GO BÀ RỊA', 'ngay' => $hnay,
	've' => array( 've_1luot' => 10 ), 'tra_tien' => array( 'mat' => 600000, 'ck' => 400000 ) );

t( '🔴 nhân viên KHÔNG ghi được doanh thu', empty( VHVH_Tien::luu( $NV, $goi_tien )['ok'] ) );

$kq = VHVH_Tien::luu( $TN, $goi_tien );
t( 'thu ngân ghi được', ! empty( $kq['ok'] ), $kq );
t( 'doanh thu do máy chủ cộng', 1000000 === $kq['ban']['tong_thu'], $kq['ban']['tong_thu'] );
/* 🔴 Người ghi tiền và người duyệt tiền là hai người. Thu ngân tự duyệt được thì sổ không còn
   ai soát. */
t( '🔴 thu ngân nộp thì phải CHỜ DUYỆT', 'cho' === $kq['ban']['tt'], $kq['ban']['tt'] );

/* 🔴 GỬI KÈM TỔNG GIẢ PHẢI VÔ TÁC DỤNG. Đây đúng là chỗ bản Firebase thủng: nó nhận con số do
   máy khách tính. Khai 10 vé mà tổng thu 0đ thì sổ kế toán nói dối đúng con số mang đi đối
   chiếu ngân hàng. */
$gian = $goi_tien;
$gian['tong_thu'] = 1;
$gian['tong_chi'] = 999999999;
$gian['khach']    = 9999;
$kq_gian = VHVH_Tien::luu( $TN, $gian );
t( '🔴 tong_thu do trang gửi lên bị BỎ QUA', 1000000 === $kq_gian['ban']['tong_thu'], $kq_gian['ban']['tong_thu'] );
t( '🔴 tong_chi gửi lên cũng bị bỏ qua',     0 === $kq_gian['ban']['tong_chi'], $kq_gian['ban']['tong_chi'] );
t( '🔴 số khách gửi lên cũng bị bỏ qua',     10 === $kq_gian['ban']['khach'], $kq_gian['ban']['khach'] );

/* 🔴 MỘT CƠ SỞ · MỘT NGÀY · MỘT DÒNG. Ghi hai lượt phải ra một dòng, không phải hai — hai dòng
   thì mọi báo cáo tháng từ đó gấp đôi mà không ai thấy sai ở đâu. */
global $wpdb;
$so_dong = (int) $wpdb->get_var( $wpdb->prepare(
	'SELECT COUNT(*) FROM ' . VHVH_DB::t( 'doanh_thu' ) . ' WHERE coso=%s AND ngay=%s',
	'GO BÀ RỊA', $hnay ) );
t( '🔴 ghi hai lượt vẫn chỉ MỘT dòng', 1 === $so_dong, $so_dong );

/* CHT ghi thì vào thẳng đã duyệt — họ chính là người duyệt. */
$kq_cht = VHVH_Tien::luu( $CHT, $goi_tien );
t( 'CHT ghi thì vào thẳng ĐÃ DUYỆT', 'duyet' === $kq_cht['ban']['tt'] );
t( 'và có tên người duyệt', 'CHT C' === $kq_cht['ban']['nguoi_duyet'] );

/* 🔴 ĐÃ DUYỆT THÌ KHOÁ. Cho sửa thẳng là con số kế toán đã mang đi đối chiếu ngân hàng có thể
   đổi sau lưng họ. */
$sua = VHVH_Tien::luu( $TN, $goi_tien );
t( '🔴 báo cáo ĐÃ DUYỆT thì không sửa được', empty( $sua['ok'] ), $sua );

/* 🔴 Mở lại chỉ dành cho quản lý — nó gỡ khoá một con số đã chốt. */
t( '🔴 CHT KHÔNG mở lại được', empty( VHVH_Tien::doi_tt( $CHT, 'GO BÀ RỊA', $hnay, 'mo_lai' )['ok'] ) );
$mo = VHVH_Tien::doi_tt( $QL, 'GO BÀ RỊA', $hnay, 'mo_lai' );
t( 'quản lý mở lại được', ! empty( $mo['ok'] ) );
t( 'mở lại thì về trạng thái chờ', 'cho' === $mo['ban']['tt'] );
/* Giữ lại dấu duyệt cũ là màn hình nói "đã duyệt bởi X" trong khi bản ghi đang mở cho người
   khác sửa — X chịu trách nhiệm cho con số họ không ký. */
t( '🔴 mở lại thì XOÁ tên người duyệt cũ', '' === $mo['ban']['nguoi_duyet'], $mo['ban']['nguoi_duyet'] );

/* Sửa được rồi thì ghi lại được. */
t( 'mở lại xong thì ghi lại được', ! empty( VHVH_Tien::luu( $TN, $goi_tien )['ok'] ) );

/* 🔴 KHÔNG GHI ĐƯỢC SANG CƠ SỞ KHÁC. */
$khac = $goi_tien; $khac['coso'] = 'VŨNG TÀU';
t( '🔴 CHT ghi sang cơ sở khác bị chối', empty( VHVH_Tien::luu( $CHT, $khac )['ok'] ) );
t( 'quản lý thì ghi được', ! empty( VHVH_Tien::luu( $QL, $khac )['ok'] ) );

/* Ngày không hợp lệ. */
$xau = $goi_tien; $xau['ngay'] = '13/09/2026';
t( 'ngày sai khuôn bị chối', empty( VHVH_Tien::luu( $TN, $xau )['ok'] ) );

/* =============================================================================================
 * 5. ĐỌC DANH SÁCH — LỌC CƠ SỞ NGAY TRONG SQL
 * =========================================================================================== */
$ds_ql  = VHVH_Tien::ds( $QL, '2000-01-01', '2999-12-31' );
$ds_cht = VHVH_Tien::ds( $CHT, '2000-01-01', '2999-12-31' );
t( 'quản lý thấy cả hai cơ sở', count( $ds_ql ) >= 2, count( $ds_ql ) );
$chi_cua_minh = true;
foreach ( $ds_cht as $x ) { if ( 'GO BÀ RỊA' !== $x['coso'] ) { $chi_cua_minh = false; } }
t( '🔴 CHT CHỈ thấy cơ sở của mình', $chi_cua_minh && count( $ds_cht ) >= 1, count( $ds_cht ) );
t( '🔴 CHT lọc sang cơ sở khác thì ra RỖNG',
	array() === VHVH_Tien::ds( $CHT, '2000-01-01', '2999-12-31', 'VŨNG TÀU' ) );

/* Người không có cơ sở nào và không phải quản lý thì không thấy gì. */
$mo_coi = array( 'ten' => 'X', 'ma_nv' => '', 'coso' => '', 'vai' => 'thu_ngan' );
t( 'người chưa gán cơ sở thì thấy rỗng', array() === VHVH_Tien::ds( $mo_coi, '2000-01-01', '2999-12-31' ) );

$tt_cht = VHVH_Tien::tom_tat( $CHT, '2000-01-01', '2999-12-31' );
$tt_ql  = VHVH_Tien::tom_tat( $QL, '2000-01-01', '2999-12-31' );
t( '🔴 tổng quan của CHT KHÔNG gồm cơ sở khác', $tt_cht['thu'] < $tt_ql['thu'], $tt_cht['thu'] . '/' . $tt_ql['thu'] );
t( 'tổng quan người chưa gán cơ sở ra 0',
	0 === VHVH_Tien::tom_tat( $mo_coi, '2000-01-01', '2999-12-31' )['thu'] );

/* =============================================================================================
 * 6. SỰ CỐ
 * =========================================================================================== */
$sc = array( 'coso' => 'GO BÀ RỊA', 'tieu_de' => 'Đèn hành lang chập chờn',
	'mo_ta' => 'Nhấp nháy khi chạy hiệu ứng', 'muc' => 'canh_bao',
	'giao_ten' => 'Nhân Viên A', 'giao_ma_nv' => 'NV01', 'han' => $hnay );

/* 🔴 Việc không gắn tên ai thì không ai làm; việc không có hạn thì không bao giờ trễ nên cũng
   không bao giờ được nhắc. Hai ô này bắt buộc. */
$thieu_nguoi = $sc; unset( $thieu_nguoi['giao_ten'] );
t( '🔴 thiếu người phụ trách thì chối', empty( VHVH_SuCo::them( $NV, $thieu_nguoi )['ok'] ) );
$thieu_han = $sc; $thieu_han['han'] = '';
t( '🔴 thiếu hạn xử lý thì chối', empty( VHVH_SuCo::them( $NV, $thieu_han )['ok'] ) );
$thieu_tieu = $sc; $thieu_tieu['tieu_de'] = '   ';
t( 'thiếu tiêu đề thì chối', empty( VHVH_SuCo::them( $NV, $thieu_tieu )['ok'] ) );

/* Nhân viên báo được sự cố của cơ sở mình — người thấy cái đèn hỏng là người báo. */
$them = VHVH_SuCo::them( $NV, $sc );
t( 'nhân viên báo được sự cố cơ sở mình', ! empty( $them['ok'] ), $them );

$sc_khac = $sc; $sc_khac['coso'] = 'VŨNG TÀU';
t( '🔴 nhân viên KHÔNG báo được cho cơ sở khác', empty( VHVH_SuCo::them( $NV, $sc_khac )['ok'] ) );

$mo_ds = VHVH_SuCo::ds( $NV, '', 'mo' );
t( 'sự cố mới nằm trong danh sách đang mở', 1 === count( $mo_ds ), count( $mo_ds ) );
t( 'mức lạ bị ép về canh_bao', 'canh_bao' === $mo_ds[0]['muc'] );

/* Hạn hôm nay thì CHƯA trễ — trễ là quá hạn, không phải đến hạn. */
t( 'hạn hôm nay thì chưa tính là trễ', 0 === (int) $mo_ds[0]['tre'] );

$id = (int) $them['id'];
t( 'đóng được sự cố', ! empty( VHVH_SuCo::doi_tt( $NV, $id, 'dong' )['ok'] ) );
t( 'đóng rồi thì rời danh sách đang mở', 0 === count( VHVH_SuCo::ds( $NV, '', 'mo' ) ) );
t( 'và nằm trong danh sách đã đóng', 1 === count( VHVH_SuCo::ds( $NV, '', 'dong' ) ) );
t( 'mở lại được', ! empty( VHVH_SuCo::doi_tt( $QL, $id, 'mo_lai' )['ok'] ) );
t( 'việc lạ thì chối', empty( VHVH_SuCo::doi_tt( $QL, $id, 'xoa_sach' )['ok'] ) );
t( 'id không có thật thì chối', empty( VHVH_SuCo::doi_tt( $QL, 999999, 'dong' )['ok'] ) );

/* 🔴 Người của cơ sở khác KHÔNG đóng được sự cố cơ sở này, dù biết id. Biết id là chuyện dễ —
   id chạy từ 1 lên. */
$nguoi_vt = array( 'ten' => 'Y', 'ma_nv' => 'NV09', 'coso' => 'VŨNG TÀU', 'vai' => 'cua_hang_truong' );
t( '🔴 người cơ sở khác KHÔNG đóng được, dù biết id',
	empty( VHVH_SuCo::doi_tt( $nguoi_vt, $id, 'dong' )['ok'] ) );
t( '🔴 và cũng không thấy nó trong danh sách', 0 === count( VHVH_SuCo::ds( $nguoi_vt, '', 'mo' ) ) );

/* =============================================================================================
 * 6b. TỔNG QUAN & ĐIỂM SỨC KHOẺ
 * =========================================================================================== */
/* 🔴 MẢNH KHÔNG CÓ DỮ LIỆU THÌ BỎ QUA, KHÔNG TÍNH 0 ĐIỂM. Cơ sở chưa được Quản trị đặt chỉ tiêu
   mà bị chấm 0 cho mảnh ấy thì điểm tụt xuống vùng đỏ vì một việc NGƯỜI KHÁC chưa làm — và cửa
   hàng trưởng ở đó không có cách nào sửa. */
t( 'chưa đặt chỉ tiêu thì trả null, KHÔNG phải 0',
	null === VHVH_Tong::chi_tieu( 'GO BÀ RỊA' ) );

/* Chỉ quản lý đặt được chỉ tiêu. */
t( '🔴 CHT KHÔNG đặt được chỉ tiêu', empty( VHVH_Tong::dat_chi_tieu( $CHT, 'GO BÀ RỊA', 5000000 )['ok'] ) );
t( 'quản lý đặt được', ! empty( VHVH_Tong::dat_chi_tieu( $QL, 'GO BÀ RỊA', 5000000 )['ok'] ) );
t( 'đặt xong thì đọc ra đúng số', 5000000 === VHVH_Tong::chi_tieu( 'GO BÀ RỊA' ) );
/* Đặt 0 = gỡ chỉ tiêu, và phải quay về null chứ không phải 0 — 0 thì phép chia điểm sẽ nổ, hoặc
   tệ hơn là ra 0 điểm cho một cơ sở chẳng làm gì sai. */
VHVH_Tong::dat_chi_tieu( $QL, 'GO BÀ RỊA', 0 );
t( '🔴 đặt 0 nghĩa là GỠ chỉ tiêu, đọc ra null', null === VHVH_Tong::chi_tieu( 'GO BÀ RỊA' ) );
VHVH_Tong::dat_chi_tieu( $QL, 'GO BÀ RỊA', 5000000 );

/* Xếp loại. */
t( '95 điểm là Tốt',        'tot'       === VHVH_Tong::xep( 95 ) );
t( '75 điểm là Cần chú ý',  'chu_y'     === VHVH_Tong::xep( 75 ) );
t( '40 điểm là Có vấn đề',  'co_van_de' === VHVH_Tong::xep( 40 ) );
/* Cơ sở mới khai chưa nhập gì mà đã bị dán nhãn đỏ thì cái nhãn ấy mất nghĩa. */
t( '🔴 chưa đủ dữ liệu KHÔNG phải "có vấn đề"', 'chua_du' === VHVH_Tong::xep( null ) );

$tq = VHVH_Tong::so( $CHT, '2000-01-01', '2999-12-31' );
t( 'tổng quan trả về số cơ sở', isset( $tq['so_coso'] ) );
t( '🔴 CHT chỉ thấy cơ sở của mình trong bảng', 1 === count( $tq['hang'] ), $tq['hang'] );
t( 'dòng cơ sở đúng tên', 'GO BÀ RỊA' === $tq['hang'][0]['coso'] );
t( 'có doanh thu đã cộng', $tq['hang'][0]['thu'] > 0 );
/* Checklist chưa ai làm thì null, KHÔNG phải 0 — 0% đọc ra là "làm tệ", còn sự thật là "chưa
   ai báo cáo". Hai chuyện khác hẳn nhau. */
t( '🔴 checklist chưa có thì null, không phải 0', null === $tq['hang'][0]['checklist'] );
t( '🔴 checklist trung bình cũng null', null === $tq['checklist_tb'] );

$tq_ql = VHVH_Tong::so( $QL, '2000-01-01', '2999-12-31' );
t( 'quản lý thấy nhiều cơ sở hơn CHT', count( $tq_ql['hang'] ) >= count( $tq['hang'] ) );

/* =============================================================================================
 * 7. SƠ ĐỒ BẢNG
 * =========================================================================================== */
$bang = VHVH_DB::bang();
t( 'có đủ 9 bảng', 9 === count( $bang ), count( $bang ) );
/* Khoá duy nhất là thứ chặn hai dòng cho một ngày — chặn ở PHP thôi thì hai lượt song song đều
   thấy "chưa có" rồi cùng chèn. */
t( '🔴 doanh_thu có khoá duy nhất (coso,ngay)',
	false !== strpos( $bang['doanh_thu'], 'UNIQUE KEY coso_ngay (coso,ngay)' ) );
t( '🔴 checklist có khoá duy nhất (coso,ngay,buoi)',
	false !== strpos( $bang['checklist'], 'UNIQUE KEY coso_ngay_buoi (coso,ngay,buoi)' ) );
t( 'kho có khoá duy nhất theo tuần',
	false !== strpos( $bang['kho'], 'UNIQUE KEY coso_tuan (coso,tuan_tu)' ) );

/* 🔴 KHUÔN dbDelta. `kiem-so-do-bang.php` bóc câu `CREATE TABLE` viết thẳng trong mã, mà sơ đồ
   ở đây lại dựng bằng cách ghép chuỗi trong `cai_dat()` — nên bài ấy KHÔNG soi tới. Soi ở đây.
   `dbDelta()` không bao giờ ném lỗi: sai khuôn thì bảng lặng lẽ không có, `$wpdb->insert()` trả
   false không kêu, màn hình chỉ thấy sổ rỗng — trông y hệt "chưa ai làm gì". */
foreach ( $bang as $ten => $than ) {
	/* Hai dấu cách sau PRIMARY KEY. Một dấu cách là dbDelta không nhận ra khoá chính — cái bẫy
	   nổi tiếng nhất của nó. */
	t( "bảng $ten: PRIMARY KEY đúng khuôn dbDelta (hai dấu cách)",
		false !== strpos( $than, 'PRIMARY KEY  (' ), $than );

	$dong = array_values( array_filter( array_map( 'trim', explode( "\n", $than ) ) ) );
	t( "bảng $ten: có ít nhất 3 dòng khai", count( $dong ) >= 3 );
	foreach ( $dong as $d ) {
		/* dbDelta tách theo ký tự xuống dòng; gộp hai cột vào một dòng là nó chỉ thấy cột đầu. */
		$sach = rtrim( $d, ',' );
		t( "bảng $ten: mỗi dòng đúng một cột/khoá — [$sach]",
			substr_count( $sach, ',' ) === 0
				|| (bool) preg_match( '/^(PRIMARY KEY|UNIQUE KEY|KEY)\s/', $sach ) );
	}
	/* Chú thích lọt vào THÂN câu là đúng lỗi đã làm bảng `lenh_tu` không dựng được ngày
	   07/09/2026 — nó không phải chú thích của PHP, nó là văn bản nằm trong chính câu SQL. */
	t( "bảng $ten: không có chú thích lọt vào thân câu",
		false === strpos( $than, '/*' ) && false === strpos( $than, '--' ) );
}

/* Không dựng lại bảng của plugin chấm công — hai sổ nhân sự song song là gốc của mọi lệch. */
foreach ( array( 'nhan_vien', 'phan_quyen', 'session', 'cham_cong', 'xin_tre' ) as $cam ) {
	t( '🔴 KHÔNG dựng lại bảng "' . $cam . '" của chấm công', ! isset( $bang[ $cam ] ) );
}

/* =============================================================================================
 * IN KẾT QUẢ
 * =========================================================================================== */
echo "\n";
if ( $TRUOT ) {
	echo 'TRƯỢT ' . count( $TRUOT ) . ":\n";
	foreach ( $TRUOT as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo 'ĐẠT: ' . $DAT . "\n";
	exit( 1 );
}
echo 'ĐẠT: ' . $DAT . " phép thử — quyền hỏi máy chủ, tiền do máy chủ cộng.\n";
