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
 * 6c. CHECKLIST ĐẦU / CUỐI NGÀY
 * =========================================================================================== */
$dm_cl = VHVH_Checklist::danh_muc( 'GO BÀ RỊA' );
t( 'danh mục mặc định có 4 khu', 4 === count( $dm_cl ), count( $dm_cl ) );
$tong_cl = VHVH_Checklist::dem_tong( $dm_cl );
t( 'đếm được tổng số mục', $tong_cl > 20, $tong_cl );

/* Tích 3 mục thật + 2 khoá bịa. */
$cl = VHVH_Checklist::luu( $NV, array( 'coso' => 'GO BÀ RỊA', 'ngay' => $hnay, 'buoi' => 'dau_ngay',
	'muc' => array( 'thu_ngan_0' => 1, 'thu_ngan_1' => 1, 'ki_thuat_0' => 1,
		'khoa_bia_dat_9' => 1, 'thu_ngan_999' => 1 ) ) );
t( 'nhân viên ghi được checklist cơ sở mình', ! empty( $cl['ok'] ), $cl );
/* 🔴 CHỈ NHẬN KHOÁ CÓ THẬT. Nhận khoá lạ thì gọi thẳng API nhét 50 khoá bịa là `xong` vọt lên 50
   trong khi cơ sở chưa làm gì — mà điểm sức khoẻ lại ăn theo đúng con số ấy. */
t( '🔴 khoá bịa bị bỏ, chỉ đếm 3 mục thật', 3 === $cl['ban']['xong'], $cl['ban']['xong'] );
t( 'tổng lấy theo danh mục', $tong_cl === $cl['ban']['tong'] );

/* 🔴 SỐ MỤC XONG DO MÁY CHỦ ĐẾM. Gửi kèm `xong` giả phải vô tác dụng — cùng lỗ với `tong_thu`. */
$cl_gian = VHVH_Checklist::luu( $NV, array( 'coso' => 'GO BÀ RỊA', 'ngay' => $hnay,
	'buoi' => 'dau_ngay', 'muc' => array( 'thu_ngan_0' => 1 ), 'xong' => 999, 'tong' => 999 ) );
t( '🔴 `xong` do trang gửi lên bị BỎ QUA', 1 === $cl_gian['ban']['xong'], $cl_gian['ban']['xong'] );
t( '🔴 `tong` gửi lên cũng bị bỏ qua', $tong_cl === $cl_gian['ban']['tong'] );

/* Một cơ sở · một ngày · một buổi · một dòng. */
$so_cl = (int) $wpdb->get_var( $wpdb->prepare(
	'SELECT COUNT(*) FROM ' . VHVH_DB::t( 'checklist' ) . ' WHERE coso=%s AND ngay=%s AND buoi=%s',
	'GO BÀ RỊA', $hnay, 'dau_ngay' ) );
t( '🔴 ghi hai lượt vẫn chỉ MỘT dòng', 1 === $so_cl, $so_cl );

/* Hai buổi là hai bản ghi riêng — đầu ngày xong rồi không có nghĩa cuối ngày cũng xong. */
VHVH_Checklist::luu( $NV, array( 'coso' => 'GO BÀ RỊA', 'ngay' => $hnay, 'buoi' => 'cuoi_ngay',
	'muc' => array( 'thu_ngan_0' => 1 ) ) );
t( 'đầu ngày và cuối ngày là hai bản ghi riêng',
	1 === VHVH_Checklist::doc( 'GO BÀ RỊA', $hnay, 'cuoi_ngay' )['xong']
	&& 1 === VHVH_Checklist::doc( 'GO BÀ RỊA', $hnay, 'dau_ngay' )['xong'] );

t( 'buổi lạ thì chối', empty( VHVH_Checklist::luu( $NV, array( 'coso' => 'GO BÀ RỊA',
	'ngay' => $hnay, 'buoi' => 'giua_trua', 'muc' => array() ) )['ok'] ) );
t( '🔴 ghi sang cơ sở khác bị chối', empty( VHVH_Checklist::luu( $NV, array(
	'coso' => 'VŨNG TÀU', 'ngay' => $hnay, 'buoi' => 'dau_ngay', 'muc' => array() ) )['ok'] ) );

/* Checklist đã có thì màn Tổng quan phải hiện phần trăm, không còn null. */
$tq_cl = VHVH_Tong::so( $CHT, '2000-01-01', '2999-12-31' );
t( 'có checklist thì tổng quan hiện phần trăm', null !== $tq_cl['hang'][0]['checklist'] );

/* =============================================================================================
 * 6d. KIỂM KHO
 * =========================================================================================== */
/* 🔴 Quy về thứ Hai ở MÁY CHỦ. Nhận thẳng ngày do trang gửi thì hai người kiểm cùng tuần mà gửi
   hai ngày khác nhau là ra hai bản ghi cho một tuần, và khoá duy nhất không cứu được. */
t( 'thứ Tư 16/09/2026 quy về thứ Hai 14/09', '2026-09-14' === VHVH_Kho::dau_tuan( '2026-09-16' ) );
t( 'chính thứ Hai thì giữ nguyên',            '2026-09-14' === VHVH_Kho::dau_tuan( '2026-09-14' ) );
t( '🔴 CHỦ NHẬT thuộc tuần TRƯỚC, không phải tuần sau',
	'2026-09-14' === VHVH_Kho::dau_tuan( '2026-09-20' ), VHVH_Kho::dau_tuan( '2026-09-20' ) );
t( 'ngày sai khuôn trả rỗng', '' === VHVH_Kho::dau_tuan( '16/09/2026' ) );

$k1 = VHVH_Kho::luu( $NV, array( 'coso' => 'GO BÀ RỊA', 'ngay' => '2026-09-16',
	'muc' => array(
		'choi'   => array( 'so' => 6 ),
		'tu_tho' => array( 'so' => 2, 'tinh' => 80 ),
		'mon_bia' => array( 'so' => 99 ),
	) ) );
t( 'ghi được sổ kho', ! empty( $k1['ok'] ), $k1 );
t( '🔴 món bịa bị bỏ', ! isset( $k1['ban']['muc']['mon_bia'] ) );
t( 'món đếm thường KHÔNG có ô tình trạng', ! isset( $k1['ban']['muc']['choi']['tinh'] ) );
t( 'món hao mòn có ô tình trạng', 80 === $k1['ban']['muc']['tu_tho']['tinh'] );

/* Gõ nhầm 1000% thì món ấy thành "tốt hơn cả mới". */
$k_qua = VHVH_Kho::luu( $NV, array( 'coso' => 'GO BÀ RỊA', 'ngay' => '2026-09-16',
	'muc' => array( 'tu_tho' => array( 'so' => 2, 'tinh' => 1000 ) ) ) );
t( '🔴 tình trạng bị kẹp về 100', 100 === $k_qua['ban']['muc']['tu_tho']['tinh'] );
$k_am = VHVH_Kho::luu( $NV, array( 'coso' => 'GO BÀ RỊA', 'ngay' => '2026-09-16',
	'muc' => array( 'tu_tho' => array( 'so' => -5, 'tinh' => -20 ) ) ) );
t( 'số âm kẹp về 0', 0 === $k_am['ban']['muc']['tu_tho']['so'] );
t( 'tình trạng âm kẹp về 0', 0 === $k_am['ban']['muc']['tu_tho']['tinh'] );

/* Mọi ngày trong cùng tuần ghi vào đúng một bản. */
VHVH_Kho::luu( $NV, array( 'coso' => 'GO BÀ RỊA', 'ngay' => '2026-09-18',
	'muc' => array( 'choi' => array( 'so' => 4 ) ) ) );
$so_kho = (int) $wpdb->get_var( $wpdb->prepare(
	'SELECT COUNT(*) FROM ' . VHVH_DB::t( 'kho' ) . ' WHERE coso=%s AND tuan_tu=%s',
	'GO BÀ RỊA', '2026-09-14' ) );
t( '🔴 kiểm hai ngày khác nhau trong cùng tuần vẫn MỘT dòng', 1 === $so_kho, $so_kho );

/* So với tuần trước — thứ người ta thật sự muốn biết. */
VHVH_Kho::luu( $NV, array( 'coso' => 'GO BÀ RỊA', 'ngay' => '2026-09-07',
	'muc' => array( 'choi' => array( 'so' => 6 ), 'tu_tho' => array( 'so' => 2, 'tinh' => 90 ) ) ) );
VHVH_Kho::luu( $NV, array( 'coso' => 'GO BÀ RỊA', 'ngay' => '2026-09-14',
	'muc' => array( 'choi' => array( 'so' => 2 ), 'tu_tho' => array( 'so' => 2, 'tinh' => 70 ) ) ) );
$ss = VHVH_Kho::so_sanh( 'GO BÀ RỊA', '2026-09-16' );
t( '🔴 thấy được mất mát so với tuần trước', -4 === $ss['choi']['lech'], $ss );
t( '🔴 và thấy được xuống cấp', -20 === $ss['tu_tho']['hao'], $ss );
t( 'món không đổi thì không kêu', ! isset( $ss['tu_tho']['lech'] ) || 0 === $ss['tu_tho']['lech'] );
t( 'chưa có tuần trước thì so sánh ra rỗng',
	array() === VHVH_Kho::so_sanh( 'GO BÀ RỊA', '2020-01-08' ) );
t( '🔴 kho: ghi sang cơ sở khác bị chối', empty( VHVH_Kho::luu( $NV, array(
	'coso' => 'VŨNG TÀU', 'ngay' => '2026-09-16', 'muc' => array() ) )['ok'] ) );

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
/* 🔴 Checklist chưa ai làm thì null, KHÔNG phải 0 — 0% đọc ra là "làm tệ", còn sự thật là "chưa
   ai báo cáo". Hai chuyện khác hẳn nhau.
   Soi trên VŨNG TÀU: cơ sở ấy có doanh thu (mục 4 ghi vào) nhưng chưa ai làm checklist bao giờ,
   đúng ca cần canh. GO BÀ RỊA thì mục 6c vừa ghi checklist nên không dùng được nữa. */
$tq_vt = null;
foreach ( VHVH_Tong::so( $QL, '2000-01-01', '2999-12-31' )['hang'] as $x ) {
	if ( 'VŨNG TÀU' === $x['coso'] ) { $tq_vt = $x; }
}
t( 'tìm được dòng VŨNG TÀU trong bảng của quản lý', null !== $tq_vt );
t( '🔴 checklist chưa có thì null, không phải 0', $tq_vt && null === $tq_vt['checklist'] );
t( '🔴 checklist trung bình của cơ sở chưa làm cũng null',
	null === VHVH_Tong::checklist_tb( array( 'VŨNG TÀU' ), current_time( 'Y-m-d' ) ) );
/* Còn cơ sở ĐÃ làm thì phải ra số, không được cũng null — null cho cả hai ca thì phép thử trên
   chẳng chứng minh được gì. */
t( 'cơ sở đã làm checklist thì ra số thật',
	null !== VHVH_Tong::checklist_tb( array( 'GO BÀ RỊA' ), current_time( 'Y-m-d' ) ) );

$tq_ql = VHVH_Tong::so( $QL, '2000-01-01', '2999-12-31' );
t( 'quản lý thấy nhiều cơ sở hơn CHT', count( $tq_ql['hang'] ) >= count( $tq['hang'] ) );

/* =============================================================================================
 * 6e. ĐÁNH GIÁ NHÂN VIÊN
 * =========================================================================================== */
$ky = current_time( 'Y-m' );
$dg = array( 'coso' => 'GO BÀ RỊA', 'ky' => $ky, 'loai' => 'vi_pham',
	'khoa' => 'di_tre_duoi_15', 'ten' => 'Nhân Viên A', 'ma_nv' => 'NV01' );

/* 🔴 Nhân viên tự ghi vi phạm cho nhau là cái sổ này thành chỗ đấu đá, và không ai tin con số
   cuối kỳ nữa. */
t( '🔴 nhân viên KHÔNG ghi được vi phạm', empty( VHVH_DanhGia::ghi( $NV, $dg )['ok'] ) );
t( '🔴 thu ngân cũng không',              empty( VHVH_DanhGia::ghi( $TN, $dg )['ok'] ) );

$l1 = VHVH_DanhGia::ghi( $CHT, $dg );
t( 'CHT ghi được', ! empty( $l1['ok'] ), $l1 );
t( 'lần đầu là lần 1', 1 === $l1['ban']['lan_thu'] );
t( 'mức nhẹ trừ 2 điểm', 2 === $l1['ban']['diem'], $l1['ban']['diem'] );
t( 'mức phạt lần 1 lấy từ bảng phạt', 'Nhắc nhở' === $l1['ban']['phat'], $l1['ban']['phat'] );

/* 🔴 Mức tăng dần theo lần — và LẦN THỨ MẤY CHỐT LÚC GHI. */
$l2 = VHVH_DanhGia::ghi( $CHT, $dg );
t( 'lần hai là lần 2', 2 === $l2['ban']['lan_thu'] );
t( 'lần hai nặng gấp rưỡi', 3 === $l2['ban']['diem'], $l2['ban']['diem'] );
t( 'và lấy mức phạt lần 2', '50.000đ' === $l2['ban']['phat'], $l2['ban']['phat'] );
$l3 = VHVH_DanhGia::ghi( $CHT, $dg );
t( 'lần ba gấp đôi', 4 === $l3['ban']['diem'], $l3['ban']['diem'] );
t( 'lần bốn vẫn lấy mức phạt cuối bảng, không nổ',
	'100.000đ + biên bản' === VHVH_DanhGia::ghi( $CHT, $dg )['ban']['phat'] );

/* Lỗi KHÁC thì đếm lần riêng — không cộng dồn mọi lỗi vào một dãy. */
$khac = $dg; $khac['khoa'] = 'sai_dong_phuc';
t( '🔴 lỗi khác đếm lần RIÊNG', 1 === VHVH_DanhGia::ghi( $CHT, $khac )['ban']['lan_thu'] );
/* Người khác cũng vậy. */
$ng2 = $dg; $ng2['ten'] = 'Người Khác'; $ng2['ma_nv'] = 'NV77';
t( '🔴 người khác đếm lần RIÊNG', 1 === VHVH_DanhGia::ghi( $CHT, $ng2 )['ban']['lan_thu'] );

t( 'lỗi không có trong bảng thì chối',
	empty( VHVH_DanhGia::ghi( $CHT, array_merge( $dg, array( 'khoa' => 'toi_bia_ra' ) ) )['ok'] ) );
t( 'thiếu tên thì chối',
	empty( VHVH_DanhGia::ghi( $CHT, array_merge( $dg, array( 'ten' => '  ' ) ) )['ok'] ) );
t( '🔴 ghi sang cơ sở khác bị chối',
	empty( VHVH_DanhGia::ghi( $CHT, array_merge( $dg, array( 'coso' => 'VŨNG TÀU' ) ) )['ok'] ) );

/* Khen thì cộng lại. */
$kh = VHVH_DanhGia::ghi( $CHT, array( 'coso' => 'GO BÀ RỊA', 'ky' => $ky, 'loai' => 'khen',
	'khoa' => 'sang_kien', 'ten' => 'Nhân Viên A', 'ma_nv' => 'NV01' ) );
t( 'ghi khen được', ! empty( $kh['ok'] ) );
t( 'khen cộng 10 điểm', 10 === $kh['ban']['diem'] );
t( 'khen không mang mức phạt', '' === $kh['ban']['phat'] );

$xep = VHVH_DanhGia::xep_loai( $CHT, $ky );
$a_row = null;
foreach ( $xep as $x ) { if ( 'NV01' === $x['ma_nv'] ) { $a_row = $x; } }
t( 'bảng xếp loại có dòng của NV01', null !== $a_row );
/* 100 − (2+3+4+4) trừ cho di_tre, − 2 cho sai_dong_phuc, + 10 khen = 95 */
t( 'điểm cộng trừ đúng', 95 === $a_row['diem'], $a_row['diem'] );
t( 'xếp loại Xuất sắc', 'xuat_sac' === $a_row['xep'], $a_row['xep'] );

/* 🔴 MỘT LỖI NGHIÊM TRỌNG THÌ TỐI ĐA TRUNG BÌNH. Gian lận doanh thu một lần mà vẫn xếp Tốt vì
   tháng ấy không vi phạm gì khác thì bảng xếp loại này chẳng còn nghĩa gì. */
VHVH_DanhGia::ghi( $CHT, array( 'coso' => 'GO BÀ RỊA', 'ky' => $ky, 'loai' => 'vi_pham',
	'khoa' => 'gian_lan_doanh_thu', 'ten' => 'Người Sạch', 'ma_nv' => 'NV88' ) );
$xep2 = VHVH_DanhGia::xep_loai( $CHT, $ky );
$s_row = null;
foreach ( $xep2 as $x ) { if ( 'NV88' === $x['ma_nv'] ) { $s_row = $x; } }
t( 'người chỉ có 1 lỗi nghiêm trọng vẫn còn 75 điểm', 75 === $s_row['diem'], $s_row['diem'] );
t( '🔴 nhưng xếp loại BỊ CHẶN ở Trung bình', 'trung_binh' === $s_row['xep'], $s_row['xep'] );
t( 'và có cờ báo lỗi nghiêm trọng', 1 === $s_row['co_nghiem_trong'] );

/* 🔴 KHÔNG XOÁ DÒNG Ở GIỮA. Xoá thì mọi dòng sau mang `lan_thu` sai và mức phạt đã chốt không
   còn khớp — mà biên bản thì đã ký. */
t( '🔴 CHT không xoá được', empty( VHVH_DanhGia::xoa( $CHT, $l1['ban']['id'] )['ok'] ) );
$xoa_giua = VHVH_DanhGia::xoa( $QL, $l1['ban']['id'] );
t( '🔴 quản lý xoá dòng Ở GIỮA cũng bị chặn', empty( $xoa_giua['ok'] ), $xoa_giua );
t( 'và câu chối nói rõ vì sao', false !== strpos( $xoa_giua['error'], 'lần thứ mấy' ) );
t( 'xoá dòng mới nhất thì được',
	! empty( VHVH_DanhGia::xoa( $QL, VHVH_DanhGia::ds( $QL, $ky )[0]['id'] )['ok'] ) );

/* Khen thì xoá lúc nào cũng được — nó không có dãy lần thứ mấy. */
t( 'xoá một lời khen thì không vướng', ! empty( VHVH_DanhGia::xoa( $QL, $kh['ban']['id'] )['ok'] ) );

/* =============================================================================================
 * 6f. TIKTOK & TRÍCH CAM
 * =========================================================================================== */
t( 'chưa khai lượt xem thì thưởng 0', 0 === VHVH_KD::thuong( null ) );
t( 'dưới bậc thấp nhất cũng 0',       0 === VHVH_KD::thuong( 999 ) );
t( '10k view → 30k',              30000 === VHVH_KD::thuong( 10000 ) );
/* 🔴 Bậc phải tra từ CAO xuống. Tra từ thấp lên thì clip 1 triệu view cũng chỉ được bậc đáy. */
t( '🔴 1 triệu view → 500k, KHÔNG phải bậc đáy', 500000 === VHVH_KD::thuong( 1000000 ),
	VHVH_KD::thuong( 1000000 ) );
t( '5 triệu view vẫn lấy bậc cao nhất', 500000 === VHVH_KD::thuong( 5000000 ) );

$tk = array( 'coso' => 'GO BÀ RỊA', 'kenh' => 'ghosthouse.gobr',
	'duong_dan' => 'https://www.tiktok.com/@ghosthouse/video/123', 'ngay_dang' => $hnay );
t( '🔴 thiếu đường dẫn thì chối',
	empty( VHVH_KD::tk_them( $NV, array_merge( $tk, array( 'duong_dan' => '' ) ) )['ok'] ) );
$tk1 = VHVH_KD::tk_them( $NV, $tk );
t( 'thêm clip được', ! empty( $tk1['ok'] ), $tk1 );

/* 🔴 Tiền do máy chủ tra bậc — nhận `tien` do trang gửi thì ai cũng tự khai 5 triệu một clip. */
$lt = VHVH_KD::tk_luot( $NV, $tk1['id'], 120000 );
t( 'khai lượt xem thì máy chủ tự tính tiền', 150000 === $lt['tien'], $lt );
$ds_tk = VHVH_KD::tk_ds( $NV, substr( $hnay, 0, 7 ) );
t( 'clip nằm trong danh sách kỳ này', 1 === count( $ds_tk ) );
t( 'tiền đã ghi vào sổ', 150000 === $ds_tk[0]['tien'] );

t( '🔴 nhân viên KHÔNG chốt được', empty( VHVH_KD::tk_chot( $NV, $tk1['id'], 1 )['ok'] ) );
t( 'CHT chốt được', ! empty( VHVH_KD::tk_chot( $CHT, $tk1['id'], 1 )['ok'] ) );
t( '🔴 chốt rồi thì nhân viên không sửa lượt xem nữa',
	empty( VHVH_KD::tk_luot( $NV, $tk1['id'], 999999 )['ok'] ) );
t( 'nhưng CHT vẫn sửa được', ! empty( VHVH_KD::tk_luot( $CHT, $tk1['id'], 60000 )['ok'] ) );

$tc = array( 'coso' => 'GO BÀ RỊA', 'so_hd' => 'HD20260916001', 'sdt' => '0901234567',
	'ngay_dk' => $hnay );
t( 'đăng ký trích cam được', ! empty( VHVH_KD::tc_them( $NV, $tc )['ok'] ) );
/* 🔴 Một hoá đơn đăng ký hai lần là một lượt đếm đôi, mà khoản ấy tính vào thành tích. */
$trung = VHVH_KD::tc_them( $NV, $tc );
t( '🔴 hoá đơn TRÙNG bị chặn', empty( $trung['ok'] ), $trung );
t( 'và câu chối nói rõ số hoá đơn', false !== strpos( $trung['error'], 'HD20260916001' ) );
/* Cùng số hoá đơn nhưng CƠ SỞ KHÁC thì cho — hai cơ sở đánh số hoá đơn riêng. */
t( 'cùng số nhưng cơ sở khác thì cho',
	! empty( VHVH_KD::tc_them( $QL, array_merge( $tc, array( 'coso' => 'VŨNG TÀU' ) ) )['ok'] ) );
t( 'thiếu số hoá đơn thì chối',
	empty( VHVH_KD::tc_them( $NV, array_merge( $tc, array( 'so_hd' => ' ' ) ) )['ok'] ) );

/* =============================================================================================
 * 6g. THÔNG BÁO & GIAO VIỆC
 * =========================================================================================== */
t( '🔴 nhân viên KHÔNG đăng được thông báo',
	empty( VHVH_Viec::tb_dang( $NV, array( 'tieu_de' => 'Thử', 'coso' => 'GO BÀ RỊA' ) )['ok'] ) );
t( 'CHT đăng cho cơ sở mình được',
	! empty( VHVH_Viec::tb_dang( $CHT, array( 'tieu_de' => 'Lịch tuần tới', 'coso' => 'GO BÀ RỊA' ) )['ok'] ) );
/* CHT gửi toàn hệ thì bảng tin đầy thông báo nội bộ của một cơ sở. */
t( '🔴 CHT KHÔNG gửi được toàn hệ',
	empty( VHVH_Viec::tb_dang( $CHT, array( 'tieu_de' => 'Toàn hệ', 'coso' => '' ) )['ok'] ) );
t( 'quản lý gửi toàn hệ được',
	! empty( VHVH_Viec::tb_dang( $QL, array( 'tieu_de' => 'Nghỉ lễ', 'coso' => '' ) )['ok'] ) );

$tb_nv = VHVH_Viec::tb_ds( $NV );
$co_toan_he = false; $co_coso_khac = false;
foreach ( $tb_nv as $x ) {
	if ( '' === $x['coso'] ) { $co_toan_he = true; }
	if ( '' !== $x['coso'] && 'GO BÀ RỊA' !== $x['coso'] ) { $co_coso_khac = true; }
}
t( 'nhân viên thấy thông báo toàn hệ', $co_toan_he );
t( '🔴 nhưng KHÔNG thấy thông báo nội bộ của cơ sở khác', ! $co_coso_khac );

$gv = array( 'coso' => 'GO BÀ RỊA', 'tieu_de' => 'Thay bóng đèn hành lang',
	'giao_ten' => 'Nhân Viên A', 'han' => $hnay );
t( '🔴 nhân viên KHÔNG giao việc được', empty( VHVH_Viec::giao( $NV, $gv )['ok'] ) );
/* Hai ô bắt buộc, cùng lý do với Sự cố. */
t( '🔴 thiếu người nhận thì chối',
	empty( VHVH_Viec::giao( $CHT, array_merge( $gv, array( 'giao_ten' => '' ) ) )['ok'] ) );
t( '🔴 thiếu hạn thì chối',
	empty( VHVH_Viec::giao( $CHT, array_merge( $gv, array( 'han' => '' ) ) )['ok'] ) );
$gv1 = VHVH_Viec::giao( $CHT, $gv );
t( 'CHT giao được', ! empty( $gv1['ok'] ) );
t( 'việc mới nằm trong danh sách chưa xong', 1 === count( VHVH_Viec::ds( $NV, '', 'chua' ) ) );
/* Người nhận tự đánh dấu xong được — bắt chờ quản lý thì danh sách lúc nào cũng đỏ. */
t( 'người nhận tự đánh dấu xong được', ! empty( VHVH_Viec::doi_tt( $NV, $gv1['id'], 'xong' )['ok'] ) );
t( 'xong rồi thì rời danh sách chưa xong', 0 === count( VHVH_Viec::ds( $NV, '', 'chua' ) ) );
t( '🔴 người cơ sở khác KHÔNG đụng được, dù biết id',
	empty( VHVH_Viec::doi_tt( $nguoi_vt, $gv1['id'], 'mo_lai' )['ok'] ) );

/* =============================================================================================
 * 6h. XUẤT BÁO CÁO CSV
 * =========================================================================================== */
$bc = VHVH_BaoCao::xuat( $QL, array( 'loai' => 'doanh_thu', 'ky' => substr( $hnay, 0, 7 ) ) );
t( 'xuất được doanh thu', ! empty( $bc['ok'] ) );
/* 🔴 Thiếu BOM thì Excel trên Windows đọc UTF-8 thành ký tự rác, và người nhận nghĩ dữ liệu
   hỏng chứ không nghĩ tại Excel. */
t( '🔴 CSV có dấu BOM ở đầu tệp', "\xEF\xBB\xBF" === substr( $bc['csv'], 0, 3 ) );
t( 'có dòng tiêu đề cột', false !== strpos( $bc['csv'], 'Doanh thu' ) );
t( 'tên tệp mang loại và kỳ', false !== strpos( $bc['ten'], 'doanh_thu' ) );
t( 'loại lạ thì chối', empty( VHVH_BaoCao::xuat( $QL, array( 'loai' => 'bia_dat' ) )['ok'] ) );

/* 🔴 Ô bắt đầu bằng =, +, -, @ bị Excel chạy như CÔNG THỨC. Đó là đường nhét công thức độc vào
   máy người mở tệp. */
t( '🔴 ô bắt đầu bằng "=" được chặn bằng dấu nháy',
	"'=SUM(A1)" === VHVH_BaoCao::o( '=SUM(A1)' ) );
t( '🔴 và cả "+", "-", "@"',
	"'+1" === VHVH_BaoCao::o( '+1' ) && "'-1" === VHVH_BaoCao::o( '-1' )
	&& "'@x" === VHVH_BaoCao::o( '@x' ) );
t( 'ô có dấu phẩy thì bọc nháy kép', '"a,b"' === VHVH_BaoCao::o( 'a,b' ) );
t( 'nháy kép trong ô thì nhân đôi', '"a""b"' === VHVH_BaoCao::o( 'a"b' ) );
t( 'ô thường thì để nguyên', 'GO BÀ RỊA' === VHVH_BaoCao::o( 'GO BÀ RỊA' ) );

/* 🔴 XUẤT ĐÚNG PHẦN ĐƯỢC PHÉP XEM. CHT xuất ra mà có cơ sở khác là lộ sổ tiền qua đường tệp —
   một cửa hậu mà chốt lọc trên màn hình không che được. */
$bc_cht = VHVH_BaoCao::xuat( $CHT, array( 'loai' => 'doanh_thu', 'ky' => substr( $hnay, 0, 7 ) ) );
t( '🔴 CSV của CHT KHÔNG chứa cơ sở khác',
	false === strpos( $bc_cht['csv'], 'VŨNG TÀU' ), $bc_cht['csv'] );
$bc_ql = VHVH_BaoCao::xuat( $QL, array( 'loai' => 'doanh_thu', 'ky' => substr( $hnay, 0, 7 ) ) );
t( 'còn CSV của quản lý thì có', false !== strpos( $bc_ql['csv'], 'VŨNG TÀU' ) );

foreach ( array( 'danh_gia', 'su_co', 'viec', 'tiktok', 'trich_cam' ) as $l ) {
	$x = VHVH_BaoCao::xuat( $QL, array( 'loai' => $l, 'ky' => substr( $hnay, 0, 7 ) ) );
	t( "xuất được báo cáo $l", ! empty( $x['ok'] ) && strlen( $x['csv'] ) > 10 );
}

/* =============================================================================================
 * 6i. KHAI CƠ SỞ RIÊNG CHO TRANG NÀY
 * ===========================================================================================
 * Anh Thắng 16/09: *"áp dụng 1 cơ sở và dùng cá nhân, nên không cần set cơ sở từ hệ thống vào
 * trong này"*. Danh mục bên chấm công là mã ĐƠN VỊ của cả công ty (21 mục) — đổ hết vào màn
 * Tổng quan thì thứ cần nhìn chìm mất giữa hai chục thẻ trống.
 * =========================================================================================== */
t( 'chưa khai thì danh sách riêng rỗng', array() === VHVH_Tong::ds_coso_khai() );

t( '🔴 CHT KHÔNG khai được danh sách cơ sở',
	empty( VHVH_Tong::dat_ds_coso( $CHT, array( 'CHỈ MỘT' ) )['ok'] ) );

$dat = VHVH_Tong::dat_ds_coso( $QL, array( 'GHOST HOUSE - GO BÀ RỊA', '  ', 'GHOST HOUSE - GO BÀ RỊA' ) );
t( 'quản lý khai được', ! empty( $dat['ok'] ) );
t( 'dòng trống bị bỏ, dòng trùng bị gộp', array( 'GHOST HOUSE - GO BÀ RỊA' ) === $dat['ds'], $dat['ds'] );

/* 🔴 CƠ SỞ ĐANG MANG DỮ LIỆU VẪN PHẢI HIỆN, dù không nằm trong danh sách khai. Giấu một cơ sở
   đang có doanh thu thật thì tiền ấy biến mất khỏi mọi báo cáo mà không ai hay. */
$dung = VHVH_Tong::ds_coso_he();
t( '🔴 cơ sở đang có dữ liệu vẫn hiện dù không khai',
	in_array( 'GO BÀ RỊA', $dung, true ), $dung );
t( 'và cơ sở mới khai cũng có', in_array( 'GHOST HOUSE - GO BÀ RỊA', $dung, true ), $dung );

/* Khai rồi thì KHÔNG đọc danh mục chấm công nữa — đó mới là mục đích. */
if ( ! class_exists( 'VHCC_NhanSu' ) ) {
	/* Bệ đỡ không có plugin chấm công; dựng một lớp giả để chứng minh đúng chốt ấy. */
	eval( 'class VHCC_NhanSu { public static function ds_coso() {
		return array( "FARM_PT", "FF_SC", "FZ_ADV_TP", "VP_KH-HCM" ); } }' );
}
$co_khai = VHVH_Tong::ds_coso_he();
t( '🔴 khai rồi thì KHÔNG kéo mã đơn vị của hệ chấm công vào',
	! in_array( 'FARM_PT', $co_khai, true ), $co_khai );

/* Xoá danh sách riêng thì quay về đọc danh mục hệ. */
VHVH_Tong::dat_ds_coso( $QL, array() );
t( 'gửi mảng rỗng là xoá danh sách riêng', array() === VHVH_Tong::ds_coso_khai() );
$khong_khai = VHVH_Tong::ds_coso_he();
t( 'xoá rồi thì đọc lại danh mục hệ', in_array( 'FARM_PT', $khong_khai, true ), $khong_khai );

/* Khai lại cho mấy phép thử sau chạy trên trạng thái gọn. */
VHVH_Tong::dat_ds_coso( $QL, array( 'GO BÀ RỊA', 'VŨNG TÀU' ) );

/* =============================================================================================
 * 7. SƠ ĐỒ BẢNG
 * =========================================================================================== */
$bang = VHVH_DB::bang();
t( 'có đủ 10 bảng', 10 === count( $bang ), count( $bang ) );
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
