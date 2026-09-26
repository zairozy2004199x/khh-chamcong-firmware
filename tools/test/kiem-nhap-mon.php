<?php
/**
 * KIỂM "NHẬP MÔN NHÂN VIÊN MỚI" (`VHCC_NhapMon`) — bảng "Bắt đầu cùng K&H", nội quy công ty có
 * phiên bản + đồng ý, dòng nhắc khi nội quy lên bản mới, màn soạn nội quy ở Tiếp nhận nhân sự.
 *
 * Anh Thắng 26/09/2026: *"Với nhân viên mới, tài khoản lần đầu kích hoạt sẽ hiện phía dưới về
 * Nội Quy Công Ty, và 1 số hướng dẫn khác"* → xem mẫu → *"làm theo mẫu này luôn đi em"*.
 *
 * Chạy: php tools/test/kiem-nhap-mon.php
 */
require_once __DIR__ . '/wp-stub.php';
$goc = dirname( dirname( __DIR__ ) );
vhcc_test_boot( $goc . '/wordpress/vhcp-cham-cong' );
define( 'VHCC_TEST', 1 );

$dat = 0; $truot = array();
function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( null === $them ? '' : ' — ' . ( is_scalar( $them ) ? substr( (string) $them, 0, 400 ) : wp_json_encode( $them, JSON_UNESCAPED_UNICODE ) ) );
}
function khoa( $tt ) { return array_map( function ( $v ) { return $v['k']; }, $tt['viec'] ); }

global $wpdb;
$CS = 'NM_SHOP';
$hn = (string) current_time( 'Y-m-d' );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'NMAD', 'ho_ten' => 'Quản Trị NM',
	'pin_dang_nhap' => '771144', 'vai_tro' => 'Admin', 'cua_hang' => $CS, 'trang_thai_lam_viec' => 'Đang làm' ) );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'NM1', 'ho_ten' => 'Nguyễn Văn Mới', 'pin_dang_nhap' => '385912',
	'cua_hang' => $CS, 'ngay_vao_lam' => gmdate( 'Y-m-d', strtotime( $hn . ' -3 days' ) ) ) );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'CU1', 'ho_ten' => 'Trần Thị Cũ', 'pin_dang_nhap' => '592731',
	'cua_hang' => $CS, 'ngay_vao_lam' => '2021-03-01' ) );
$MOI = array( 'ma_nv' => 'NM1', 'name' => 'Nguyễn Văn Mới' );
$CU  = array( 'ma_nv' => 'CU1', 'name' => 'Trần Thị Cũ' );

/* ── 1. Nội quy mặc định + chữ ↔ mục ── */
$nq = VHCC_NhapMon::noi_quy();
t( '   nội quy soạn sẵn: bản 1.0, 13 mục, chưa lưu', '1.0' === $nq['ban'] && 13 === count( $nq['muc'] ) && '' === $nq['luc'], $nq['ban'] );
$tieu = array_map( function ( $m ) { return $m['tieu']; }, $nq['muc'] );
/* BLLĐ 2019 Điều 118 khoản 2 — nội quy PHẢI có đủ các nội dung này. */
foreach ( array( 'Giờ làm việc', 'Tạm thời chuyển', 'Trách nhiệm vật chất', 'quấy rối tình dục', 'Thẩm quyền', 'kỷ luật', 'Bảo mật', 'An toàn trẻ em', 'phòng cháy' ) as $can ) {
	t( '🔴 nội quy soạn sẵn có mục "' . $can . '" (Điều 118)', (bool) array_filter( $tieu, function ( $x ) use ( $can ) { return false !== mb_stripos( $x, $can ); } ), $tieu );
}
$chu_nq = VHCC_NhapMon::ra_chu( $nq );
t( '🔴 nói rõ không phạt tiền / cắt lương thay kỷ luật (Điều 127)', false !== strpos( $chu_nq, 'không phạt tiền, không cắt lương' ) );
t( '   bồi thường trừ lương không quá 30%', false !== strpos( $chu_nq, 'không quá 30%' ) );
t( '   đủ bốn hình thức kỷ luật Điều 124', false !== strpos( $chu_nq, 'khiển trách; kéo dài thời hạn nâng lương không quá 6 tháng; cách chức; sa thải' ) );
t( '   thời gian đọc tính theo độ dài', VHCC_NhapMon::phut_doc( $nq ) >= 5 && 2 === VHCC_NhapMon::phut_doc( array( 'muc' => array( array( 'tieu' => 'a', 'dong' => array( 'ngắn' ) ) ) ) ) );
t( '🔴 chữ ra rồi đọc lại đúng từng mục', VHCC_NhapMon::doc_chu( VHCC_NhapMon::ra_chu( $nq ) ) === $nq['muc'] );
$d = VHCC_NhapMon::doc_chu( "dòng lạc đầu\n## A\n* một\n\n## Rỗng\n## B\n• hai\n- ba" );
t( '   dòng trước tiêu đề vào "Quy định chung"; mục không có dòng bị bỏ',
	3 === count( $d ) && 'Quy định chung' === $d[0]['tieu'] && array( 'hai', 'ba' ) === $d[2]['dong'], $d );

/* ── 2. Người mới (không qua Tiếp nhận) ── */
$tt = VHCC_NhapMon::trang_thai( $MOI );
t( '🔴 người mới vào 3 ngày -> thấy bảng', $tt['hien'] && $tt['moi'], $tt );
t( '   không qua Tiếp nhận thì không bày "đổi PIN" / "ký HĐ"', array( 'kich', 'nq', 'cai', 'cham' ) === khoa( $tt ), khoa( $tt ) );
t( '   kích hoạt tự tích; còn lại chưa', 1 === $tt['xong'] && 4 === $tt['tong'], $tt );
t( '   "cài app" là việc tự tích', ! empty( $tt['viec'][2]['tuTich'] ) && empty( $tt['viec'][1]['tuTich'] ) );
t( '   gửi kèm nội quy để trạm vẽ', 13 === count( $tt['noiQuy']['muc'] ) && '' === $tt['noiQuy']['dongY'] );
$tc = VHCC_NhapMon::trang_thai( $CU );
t( '🔴 người cũ không thấy bảng', ! $tc['hien'] && ! $tc['moi'], $tc );
t( '🔴 bản nháp chưa lưu -> KHÔNG nhắc người cũ', ! $tc['nhacLai'], $tc );
t( '   mã rỗng -> không lỗi, không bảng', ! VHCC_NhapMon::trang_thai( array() )['hien'] );

/* ── 3. Đồng ý nội quy ── */
$r = VHCC_NhapMon::dong_y( $MOI, '0.9', '1.2.3.4', 'UA' );
t( '🔴 đồng ý sai bản -> chối', empty( $r['ok'] ) && false !== strpos( $r['error'], '1.0' ), $r );
$r = VHCC_NhapMon::dong_y( $MOI, '1.0', '1.2.3.4', 'Mozilla/5.0 Thử' );
t( '🔴 đồng ý đúng bản -> ghi', ! empty( $r['ok'] ) && '1.0' === $r['ban'] && '' !== $r['luc'], $r );
$x = VHCC_NhapMon::dong_y_cua( 'nm1' );
t( '   ghi bản, lúc, tên, IP, thiết bị', $x && '1.0' === $x['ban'] && '1.2.3.4' === $x['ip'] && 'Mozilla/5.0 Thử' === $x['ua'] && 'Nguyễn Văn Mới' === $x['ten'], $x );
$r2 = VHCC_NhapMon::dong_y( $MOI, '1.0', '9.9.9.9', 'khác' );
t( '   bấm lại -> không ghi thêm, giữ giờ cũ', ! empty( $r2['ok'] ) && 1 === count( VHCC_NhapMon::ds_dong_y() ) && $r2['luc'] === $r['luc'] );
t( '   tài khoản không mã NV -> chối', empty( VHCC_NhapMon::dong_y( array( 'name' => 'x' ), '1.0' )['ok'] ) );
$tt = VHCC_NhapMon::trang_thai( $MOI );
t( '   bảng: nội quy đã xong, 2/4', 2 === $tt['xong'] && $tt['viec'][1]['xong'] && '' !== $tt['noiQuy']['dongY'], $tt );

/* ── 4. Tự tích / tự biết ── */
t( '   chỉ tích được "cai" và "an"', empty( VHCC_NhapMon::tich( $MOI, 'cham', true )['ok'] ) && empty( VHCC_NhapMon::tich( $MOI, 'nq', true )['ok'] ) );
VHCC_NhapMon::tich( $MOI, 'cai', true );
t( '🔴 tích "cài app" -> 3/4', 3 === VHCC_NhapMon::trang_thai( $MOI )['xong'] );
VHCC_NhapMon::tich( $MOI, 'cai', false );
t( '   bỏ tích được', 2 === VHCC_NhapMon::trang_thai( $MOI )['xong'] );
VHCC_NhapMon::tich( $MOI, 'cai', true );
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => $CS, 'ngay' => $hn, 'ma_nv' => 'NM1', 'hau_to' => '', 'ho_ten' => 'x',
	'gio_vao_giay' => 28800, 'gio_ra_giay' => null, 'nguon' => 'online' ) );
$tt = VHCC_NhapMon::trang_thai( $MOI );
t( '🔴 có lượt chấm vào -> "chấm lượt đầu" tự tích, 4/4', 4 === $tt['xong'] && $tt['viec'][3]['xong'], $tt );
t( '   xong hết vẫn hiện (thẻ chúc mừng) tới khi tự ẩn', $tt['hien'] );
VHCC_NhapMon::tich( $MOI, 'an', true );
$tt = VHCC_NhapMon::trang_thai( $MOI );
t( '🔴 ẩn bảng -> không hiện, không nhắc', ! $tt['hien'] && ! $tt['nhacLai'], $tt );

/* ── 5. Soạn nội quy ở màn Tiếp nhận (qua web thật) ── */
update_option( 'vhcc_nguon_nguoidung', 'ho_so' );
VHCC_Auth::mo_khoa();
$kq = VHCC_Auth::login( '771144' );
t( 'dựng cảnh: Admin đăng nhập', ! empty( $kq['ok'] ), $kq );
$tok = $kq['token'];
$web = function ( $get, $post = array() ) use ( $tok ) {
	$_COOKIE = array( VHCC_Web::COOKIE => $tok );
	$_GET  = $get;
	$_POST = $post ? array_merge( array( 'ky' => VHCC_Web::chu_ky( $tok ) ), $post ) : array();
	ob_start(); VHCC_Web::phuc_vu(); $h = ob_get_clean();
	$_GET = array(); $_POST = array(); $_COOKIE = array();
	return $h;
};
$G = array( 'man' => 'tiep_nhan' );
$h = $web( $G );
t( '🔴 màn Tiếp nhận có thẻ "Nội quy công ty" + bản nháp nói rõ', false !== strpos( $h, '📜 Nội quy công ty' ) && false !== strpos( $h, 'BẢN SOẠN SẴN CHO KHU VUI CHƠI' ) );
t( '   ô soạn chứa sẵn nội quy dạng chữ', false !== strpos( $h, esc_textarea( '## Giờ làm việc, nghỉ ngơi & chấm công' ) ) );
t( '🔴 bảng "Ai đã đồng ý" có NM1', false !== strpos( $h, 'Ai đã đồng ý' ) && false !== strpos( $h, 'NM1' ) && false !== strpos( $h, '1.2.3.4' ) );

$web( $G, array( 'viec' => 'tn_noi_quy', 'nq_chu' => VHCC_NhapMon::ra_chu(), 'nq_ngay' => '2026-10-01' ) );
$nq = VHCC_NhapMon::noi_quy();
t( '🔴 lưu nguyên nội dung -> giữ bản 1.0, có ngày áp dụng, có người lưu', '1.0' === $nq['ban'] && '2026-10-01' === $nq['apDung'] && '' !== $nq['luc'], $nq );
$tc = VHCC_NhapMon::trang_thai( $CU );
t( '🔴 nội quy đã lưu thật -> người cũ chưa đồng ý được nhắc', ! $tc['hien'] && $tc['nhacLai'], $tc );

$chu2 = VHCC_NhapMon::ra_chu() . "\n\n## Vệ sinh\n- Dọn khu vực trước khi giao ca.";
$web( $G, array( 'viec' => 'tn_noi_quy', 'nq_chu' => $chu2, 'nq_ngay' => '' ) );
$nq = VHCC_NhapMon::noi_quy();
t( '🔴 đổi nội dung khi đã có người đồng ý -> LÊN BẢN 1.1', '1.1' === $nq['ban'] && 14 === count( $nq['muc'] ), $nq['ban'] );
t( '   ngày áp dụng để trống thì giữ ngày cũ', '2026-10-01' === $nq['apDung'] );
$tt = VHCC_NhapMon::trang_thai( $MOI );
t( '🔴 người đã đồng ý bản cũ -> được nhắc đồng ý lại', ! $tt['hien'] && $tt['nhacLai'] && '' === $tt['noiQuy']['dongY'], $tt );
$r = VHCC_NhapMon::dong_y( $MOI, '1.0' );
t( '   đồng ý bản cũ bị chối, nói số bản mới', empty( $r['ok'] ) && false !== strpos( $r['error'], '1.1' ), $r );
VHCC_NhapMon::dong_y( $MOI, '1.1' );
t( '   đồng ý bản mới -> hết nhắc', ! VHCC_NhapMon::trang_thai( $MOI )['nhacLai'] );
t( '   lịch sử giữ cả hai lần', 2 === count( VHCC_NhapMon::ds_dong_y() ) );

$web( $G, array( 'viec' => 'tn_noi_quy', 'nq_chu' => str_replace( 'giao ca', 'giao ca.', $chu2 ), 'nq_giu' => '1' ) );
t( '🔴 tích "giữ nguyên bản" -> vẫn 1.1, người đã đồng ý vẫn đủ', '1.1' === VHCC_NhapMon::noi_quy()['ban'] && ! VHCC_NhapMon::trang_thai( $MOI )['nhacLai'] );
$web( $G, array( 'viec' => 'tn_noi_quy', 'nq_chu' => $chu2 . "\n- Thêm một dòng." ) );
t( '   đổi tiếp (có người đồng ý 1.1) -> 1.2', '1.2' === VHCC_NhapMon::noi_quy()['ban'] );
$web( $G, array( 'viec' => 'tn_noi_quy', 'nq_chu' => $chu2 . "\n- Thêm hai dòng." ) );
t( '🔴 chưa ai đồng ý 1.2 -> sửa thẳng, không lên bản', '1.2' === VHCC_NhapMon::noi_quy()['ban'] );
$h = $web( $G, array( 'viec' => 'tn_noi_quy', 'nq_chu' => "chỉ có chữ\n\n" ) );
t( '   nội dung không có mục nào hợp lệ vẫn lưu thành "Quy định chung"', 'Quy định chung' === VHCC_NhapMon::noi_quy()['muc'][0]['tieu'] );
$r = VHCC_NhapMon::dat_noi_quy( $CU, "## X\n- y" );
t( '🔴 nhân viên thường không soạn được nội quy', empty( $r['ok'] ), $r );
t( '   nội dung rỗng -> chối', empty( VHCC_NhapMon::dat_noi_quy( array( 'ma_nv' => 'NMAD', 'vai_tro' => 'Admin' ), "  \n" )['ok'] ) );

/* ── 6. Người đi qua Tiếp nhận: đổi PIN + ký HĐ ── */
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'TN9', 'ho_ten' => 'Lê Thị Tiếp', 'pin_dang_nhap' => '640281',
	'cua_hang' => $CS, 'ngay_vao_lam' => $hn ) );
$bam = new ReflectionMethod( 'VHCC_TiepNhan', 'bam_pin' );
$bam->setAccessible( true );
VHCC_Luong::dat_cai_dat( VHCC_TiepNhan::O_SO, array( 'tn9' => array( 'ma' => 'TN9', 'luc' => current_time( 'mysql' ), 'boi' => 'Quản Trị NM',
	'pinHash' => $bam->invoke( null, 'TN9', '640281' ), 'buoc' => array(),
	'hd' => array( 'ho_ten' => 'Lê Thị Tiếp', 'chuc_vu' => 'Nhân viên KVC', 'coso' => $CS ) ) ), array( 'name' => 'test' ) );
$TN = array( 'ma_nv' => 'TN9', 'name' => 'Lê Thị Tiếp' );
$tt = VHCC_NhapMon::trang_thai( $TN );
t( '🔴 qua Tiếp nhận -> đủ 6 việc như mẫu', array( 'kich', 'nq', 'pin', 'cai', 'cham', 'hd' ) === khoa( $tt ), khoa( $tt ) );
t( '   còn PIN hệ thống cấp -> "đổi PIN" chưa xong', false === VHCC_TiepNhan::pin_da_doi( 'TN9' ) && ! $tt['viec'][2]['xong'] );
t( '   việc ký HĐ kèm link bộ hồ sơ', false !== strpos( (string) $tt['viec'][5]['link'], 'vhcc_nhanviec=1' ) );
$wpdb->update( VHCC_DB::t( 'nhan_vien' ), array( 'pin_dang_nhap' => '918273' ), array( 'ma_nv' => 'TN9' ) );
t( '🔴 đổi PIN -> tự tích', true === VHCC_TiepNhan::pin_da_doi( 'TN9' ) && VHCC_NhapMon::trang_thai( $TN )['viec'][2]['xong'] );
t( '   người không qua Tiếp nhận -> pin_da_doi false', false === VHCC_TiepNhan::pin_da_doi( 'NM1' ) );
$so = VHCC_Luong::cai_dat( VHCC_TiepNhan::O_SO, array() );
$so['tn9']['nvKy'] = array( 'ten' => 'Lê Thị Tiếp', 'luc' => current_time( 'mysql' ) );
VHCC_Luong::dat_cai_dat( VHCC_TiepNhan::O_SO, $so, array( 'name' => 'test' ) );
t( '🔴 ký HĐ điện tử -> tự tích', VHCC_NhapMon::trang_thai( $TN )['viec'][5]['xong'] );
$h = $web( $G );
t( '   bảng "Đã tiếp nhận" nói trạng thái nội quy', false !== strpos( $h, '📜 chưa đồng ý nội quy bản 1.2' ) );
VHCC_NhapMon::dong_y( $TN, '1.2' );
t( '   … và đổi khi đã đồng ý', false !== strpos( $web( $G ), '📜 đã đồng ý nội quy ' ) );

/* ── 6b. 🧪 Chế độ thử ── */
$tc = VHCC_NhapMon::trang_thai( $CU );
t( '   dựng cảnh: người cũ không thấy bảng', ! $tc['hien'] && ! $tc['thu'] );
$h = $web( $G );
t( '🔴 màn Tiếp nhận có thẻ "Chế độ thử nhập môn", đang tắt', false !== strpos( $h, '🧪 Chế độ thử nhập môn' ) && false !== strpos( $h, '— đang tắt' ) );
$web( $G, array( 'viec' => 'tn_nm_thu', 'nm_thu' => 'CU1, KHONGCO9' ) );
t( '🔴 mã không có hồ sơ -> chối cả danh sách', array() === VHCC_NhapMon::ds_thu() );
$web( $G, array( 'viec' => 'tn_nm_thu', 'nm_thu' => 'cu1, NMAD' ) );
t( '🔴 bật thử cho CU1 + NMAD', array( 'cu1', 'nmad' ) === VHCC_NhapMon::ds_thu(), VHCC_NhapMon::ds_thu() );
$tc = VHCC_NhapMon::trang_thai( $CU );
t( '🔴 người cũ đang thử -> thấy bảng như người mới, có cờ thử', $tc['hien'] && $tc['moi'] && $tc['thu'], $tc );
VHCC_NhapMon::dong_y( $CU, VHCC_NhapMon::noi_quy()['ban'] );
VHCC_NhapMon::tich( $CU, 'cai', true );
VHCC_NhapMon::tich( $CU, 'an', true );
t( '   dựng cảnh: CU1 đã đồng ý, tích, ẩn', ! VHCC_NhapMon::trang_thai( $CU )['hien'] && null !== VHCC_NhapMon::dong_y_cua( 'CU1' ) );
$h = $web( $G );
t( '   có nút làm lại cho từng mã thử', false !== strpos( $h, '↺ Làm lại từ đầu: CU1' ) && false !== strpos( $h, 'đang bật cho CU1, NMAD' ) );
$web( $G, array( 'viec' => 'tn_nm_lai', 'tn_ma' => 'CU1' ) );
$tc = VHCC_NhapMon::trang_thai( $CU );
t( '🔴 làm lại -> bảng hiện lại, nội quy + cài app về chưa xong', $tc['hien'] && null === VHCC_NhapMon::dong_y_cua( 'CU1' ) && ! $tc['viec'][2]['xong'], $tc['viec'] );
$so_dy = count( VHCC_NhapMon::ds_dong_y( 999 ) );
$r = VHCC_NhapMon::lam_lai( array( 'ma_nv' => 'NMAD', 'vai_tro' => 'Admin' ), 'NM1' );
t( '🔴 KHÔNG làm lại được cho nhân viên thật (lần đồng ý là bằng chứng)', empty( $r['ok'] ) && $so_dy === count( VHCC_NhapMon::ds_dong_y( 999 ) ) && null !== VHCC_NhapMon::dong_y_cua( 'NM1' ), $r );
t( '   nhân viên thường không bật được chế độ thử', empty( VHCC_NhapMon::dat_thu( $CU, 'CU1' )['ok'] ) );
$web( $G, array( 'viec' => 'tn_nm_thu', 'nm_thu' => '' ) );
t( '🔴 xoá trống -> tắt, người cũ hết thấy bảng', array() === VHCC_NhapMon::ds_thu() && ! VHCC_NhapMon::trang_thai( $CU )['hien'] );

/* ── 7. Trạm ── */
$tpl  = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/templates/tram.php' );
$tram = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-tram.php' );
t( '🔴 bảng nhập môn nằm ngay dưới lời chào', (bool) preg_match( '/<div id="loiChao"[^>]*><\/div>\s*<!--.*?-->\s*<div id="nhapMon" class="nm an"><\/div>/s', $tpl ) );
t( '   có màn Nội quy: ô cam kết + nút Đồng ý (khoá tới khi tích)', false !== strpos( $tpl, '<div id="mNoiQuy" class="mn an">' )
	&& false !== strpos( $tpl, '<input id="nqCam" type="checkbox">' ) && false !== strpos( $tpl, '<button id="btNqDongY" class="chinh" disabled>' ) );
t( '🔴 tab Tôi mở lại được nội quy', false !== strpos( $tpl, 'id="btMoNqToi"' ) && false !== strpos( $tpl, "el('btMoNqToi').addEventListener('click'" ) );
t( '   napToi vẽ bảng', false !== strpos( $tpl, 'veNhapMon(j.nhapMon);' ) );
t( '   trạm gọi đúng hai việc', false !== strpos( $tpl, "goi('noi_quy_dong_y'" ) && false !== strpos( $tpl, "goi('nhap_mon_tich'" ) );
t( '   trạm gắn nhãn 🧪 thử khi đang ở chế độ thử', false !== strpos( $tpl, "(nm.thu ? ' <span class=\"nm-thu\">🧪 thử</span>' : '')" ) );
t( '   hướng dẫn nhanh 4 thẻ vuốt ngang', false !== strpos( $tpl, 'var NM_HD = [' ) && false !== strpos( $tpl, 'scroll-snap-type:x mandatory' ) );
t( '🔴 máy chủ gửi `nhapMon` trong lượt `toi` + nhận hai việc', false !== strpos( $tram, "\$tt['nhapMon'] = VHCC_NhapMon::trang_thai( \$u );" )
	&& false !== strpos( $tram, "'noi_quy_dong_y' === \$viec" ) && false !== strpos( $tram, "'nhap_mon_tich' === \$viec" ) );
t( '   đồng ý ghi IP + thiết bị từ máy chủ, không tin trình duyệt', false !== strpos( $tram, "\$_SERVER['HTTP_USER_AGENT']" ) );

echo "\n";
if ( $truot ) {
	echo 'TRƯỢT ' . count( $truot ) . ":\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "✓ SẠCH — $dat phép thử — nhập môn nhân viên mới + nội quy công ty.\n";
