<?php
/**
 * KIỂM ĐỐI CHIẾU KHUÔN MẶT (VHCC_Mat).
 *
 * 🔴 VÌ SAO PHẢI KIỂM RIÊNG, VÀ KIỂM KỸ. Đây là thứ duy nhất trong plugin có thể ĐỔ OAN cho
 * một người: một dòng cờ "ảnh không khớp mặt" đi thẳng tới quản lý. Sai ở đây không làm trang
 * trắng, không làm ai mất công — nó làm người ta bị nghi ngờ. Nên bài này canh ba thứ:
 *
 *   1. Nó KHÔNG BAO GIỜ chặn được lượt chấm công (phương án B anh Thắng chọn).
 *   2. Vùng "không biết" thì IM, không gắn cờ — cờ phải hiếm thì mới có người đọc.
 *   3. Dữ liệu rác không lọt vào làm mẫu — mẫu rác là người đó bị gắn cờ mỗi ngày về sau.
 *
 * Chạy: php tools/test/kiem-mat.php
 */

require_once __DIR__ . '/wp-stub.php';

$goc = dirname( dirname( __DIR__ ) );
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );
vhcc_test_boot( $goc . '/wordpress/vhcp-cham-cong' );

$dat = 0; $truot = array();
function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( null === $them ? '' : ' — ' . ( is_scalar( $them ) ? $them : wp_json_encode( $them ) ) );
}

global $wpdb;

/* Dựng một dãy 128 số quanh một "hạt giống", lệch đi $lech mỗi chiều.
   Khoảng cách Euclid giữa hai dãy lệch đều = sqrt(128) * lech ≈ 11.31 * lech. */
function vec( $goc_so, $lech = 0.0 ) {
	$v = array();
	for ( $i = 0; $i < 128; $i++ ) { $v[] = $goc_so + ( $i % 7 ) * 0.01 + $lech; }
	return $v;
}
/** Dãy lệch đúng $d khoảng cách Euclid so với vec($goc_so). */
function vec_cach( $goc_so, $d ) {
	return vec( $goc_so, $d / sqrt( 128 ) );
}

$U = array( 'ma_nv' => 'NV001', 'ho_ten' => 'Trần Văn A', 'coso' => 'VIVO' );

/* ============================================================ 1. ĐỌC DÃY ĐẶC TRƯNG */
t( 'dãy 128 số hợp lệ đọc được', is_array( VHCC_Mat::doc_vector( vec( 0.1 ) ) ) );
t( 'nhận cả chuỗi JSON', is_array( VHCC_Mat::doc_vector( wp_json_encode( vec( 0.1 ) ) ) ) );
t( 'dãy 127 số bị chối', null === VHCC_Mat::doc_vector( array_slice( vec( 0.1 ), 0, 127 ) ) );
t( 'dãy 129 số bị chối', null === VHCC_Mat::doc_vector( array_merge( vec( 0.1 ), array( 1 ) ) ) );
t( 'không phải mảng thì chối', null === VHCC_Mat::doc_vector( 'abc' ) );
t( 'rỗng thì chối', null === VHCC_Mat::doc_vector( null ) && null === VHCC_Mat::doc_vector( array() ) );

/* 🔴 Dãy ĐỦ số chiều nhưng chứa giá trị vô lý cũng phải chối. Một dãy rác lọt vào làm mẫu là
   người đó bị gắn cờ mọi ngày về sau, mà nhìn bảng chỉ thấy "mẫu đã có". */
$rac = vec( 0.1 ); $rac[5] = 'xin chào';
t( 'dãy có chữ bị chối', null === VHCC_Mat::doc_vector( $rac ) );
$rac = vec( 0.1 ); $rac[5] = INF;
t( 'dãy có vô cực bị chối', null === VHCC_Mat::doc_vector( $rac ) );
$rac = vec( 0.1 ); $rac[5] = NAN;
t( 'dãy có NaN bị chối', null === VHCC_Mat::doc_vector( $rac ) );
$rac = vec( 0.1 ); $rac[5] = 1e9;
t( 'dãy có số khổng lồ bị chối', null === VHCC_Mat::doc_vector( $rac ) );

/* ============================================================ 2. KHOẢNG CÁCH */
$a = VHCC_Mat::doc_vector( vec( 0.1 ) );
t( 'chính nó cách chính nó bằng 0', abs( VHCC_Mat::khoang_cach( $a, $a ) ) < 1e-9 );
$b = VHCC_Mat::doc_vector( vec_cach( 0.1, 0.5 ) );
t( 'dựng được dãy cách đúng 0,5', abs( VHCC_Mat::khoang_cach( $a, $b ) - 0.5 ) < 1e-6,
	VHCC_Mat::khoang_cach( $a, $b ) );
t( 'khoảng cách đối xứng',
	abs( VHCC_Mat::khoang_cach( $a, $b ) - VHCC_Mat::khoang_cach( $b, $a ) ) < 1e-9 );

/* Gộp: mẫu cũ nặng n phần, dãy mới 1 phần — mẫu mới phải nằm GIỮA và nghiêng về mẫu cũ. */
$g = VHCC_Mat::gop( $a, $b, 3 );
$d_cu  = VHCC_Mat::khoang_cach( $a, $g );
$d_moi = VHCC_Mat::khoang_cach( $b, $g );
t( 'gộp xong vẫn đủ 128 chiều', 128 === count( $g ) );
t( 'gộp thì nghiêng về mẫu cũ', $d_cu < $d_moi, array( $d_cu, $d_moi ) );
t( 'và nằm giữa hai dãy', abs( ( $d_cu + $d_moi ) - 0.5 ) < 1e-6 );

/* ============================================================ 3. LẤY MẪU LẦN ĐẦU */
vhcc_dung_bang();
$r = VHCC_Mat::soi( $U, vec( 0.1 ), '2026-08-20', 'VIVO' );
t( 'chưa có mẫu -> lấy tấm này làm mẫu', 'lay_mau' === $r['ket_qua'], $r );
$mau = VHCC_Mat::mau( 'NV001' );
t( 'mẫu đã vào kho', is_array( $mau ) );
/* 🔴 CHỜ DUYỆT, không tin ngay: nếu chính ngày đầu là ngày có người chấm hộ thì mẫu ghi lại
   khuôn mặt của người chấm hộ, và từ đó hệ thống gắn cờ NGƯỢC. */
t( 'mẫu đầu tiên ở trạng thái CHỜ DUYỆT', 'cho' === $mau['trang_thai'], $mau['trang_thai'] );
t( 'ghi lại lấy từ đâu', '2026-08-20' === $mau['nguon_ngay'] && 'VIVO' === $mau['nguon_coso'] );
t( 'đếm gộp bắt đầu từ 1', 1 === (int) $mau['so_lan'] );
t( 'lấy mẫu thì KHÔNG gắn cờ nào',
	0 === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'ghi_chu' ) ) );

/* ============================================================ 4. KHỚP / KHÔNG BIẾT / LỆCH */
/* ⚠️ MỖI TÌNH HUỐNG MỘT MÃ NV RIÊNG. Gộp mẫu làm mẫu DỊCH CHUYỂN, nên đo lượt sau bằng
   khoảng cách tính từ dãy gốc là sai — chính em vừa vấp: dựng dãy cách 0,43 mà máy báo 0,33
   vì mẫu đã nhích về phía lượt trước. Dùng chung một mã cho nhiều tình huống là phép thử đo
   nhầm thứ mình định đo. */
function gieo_mau( $ma, $goc_so ) {
	$u = array( 'ma_nv' => $ma, 'ho_ten' => 'Người ' . $ma, 'coso' => 'VIVO' );
	VHCC_Mat::soi( $u, vec( $goc_so ), '2026-08-20', 'VIVO' );
	return $u;
}

/* Rất giống -> gộp cho mẫu chín dần. */
$U_GOP = gieo_mau( 'NV_GOP', 0.1 );
$r = VHCC_Mat::soi( $U_GOP, vec_cach( 0.1, 0.20 ), '2026-08-21', 'VIVO' );
t( 'rất giống -> khớp', 'khop' === $r['ket_qua'], $r );
t( 'và được gộp vào mẫu', ! empty( $r['gop'] ), $r );
t( 'số lần gộp tăng', 2 === (int) VHCC_Mat::mau( 'NV_GOP' )['so_lan'] );

/* Giống vừa -> khớp nhưng KHÔNG gộp (chưa đủ chắc để động vào mẫu). */
$U_VUA = gieo_mau( 'NV_VUA', 0.1 );
$r = VHCC_Mat::soi( $U_VUA, vec_cach( 0.1, 0.43 ), '2026-08-22', 'VIVO' );
t( 'giống vừa -> vẫn khớp', 'khop' === $r['ket_qua'], $r );
t( 'nhưng KHÔNG gộp vào mẫu', empty( $r['gop'] ), $r );
t( 'và mẫu KHÔNG bị động tới', 1 === (int) VHCC_Mat::mau( 'NV_VUA' )['so_lan'] );

/* 🔴 VÙNG KHÔNG BIẾT (0,45–0,60): không kết luận, và KHÔNG gắn cờ. Gắn cờ ở đây là đẻ ra một
   đống cờ xem xong chẳng kết luận được gì — một tuần sau không ai mở màn cờ nữa. */
$U_GIUA = gieo_mau( 'NV_GIUA', 0.1 );
$truoc_co = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'ghi_chu' ) );
$r = VHCC_Mat::soi( $U_GIUA, vec_cach( 0.1, 0.52 ), '2026-08-23', 'VIVO' );
t( 'vùng giữa -> "khó nói"', 'kho_noi' === $r['ket_qua'], $r );
t( 'và KHÔNG gắn cờ', $truoc_co === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'ghi_chu' ) ) );
t( 'vùng giữa cũng KHÔNG động vào mẫu', 1 === (int) VHCC_Mat::mau( 'NV_GIUA' )['so_lan'] );

/* Lệch hẳn -> gắn cờ. */
$r = VHCC_Mat::soi( $U, vec_cach( 0.1, 0.95 ), '2026-08-24', 'VIVO' );
t( 'lệch hẳn -> gắn cờ', 'lech' === $r['ket_qua'], $r );
t( 'và trả về mã cờ', ! empty( $r['flagId'] ), $r );
$co = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . VHCC_DB::t( 'ghi_chu' )
	. ' WHERE flag_id=%s', $r['flagId'] ), ARRAY_A );
t( 'cờ vào bảng thật', is_array( $co ) );
t( 'cờ ghi đúng ngày của lượt chấm', '2026-08-24' === $co['ngay'], $co );
t( 'cờ ghi đúng mã NV', 'NV001' === $co['ma_nv'] );
t( 'cờ ghi rõ do MÁY gắn, không mượn tên người',
	strpos( $co['nguoi_gan'], 'Hệ thống' ) !== false, $co['nguoi_gan'] );
t( 'cờ ở trạng thái Cần kiểm', 'Cần kiểm' === $co['trang_thai'] );
t( 'cờ bảo mở ảnh ra xem', strpos( $co['ghi_chu'], 'Mở ảnh' ) !== false, $co['ghi_chu'] );
/* Mẫu chưa duyệt thì cờ phải NÓI RA, vì rất có thể chính tấm mẫu mới là tấm sai. */
t( 'cờ cảnh báo mẫu CHƯA ĐƯỢC DUYỆT',
	strpos( $co['ghi_chu'], 'CHƯA ĐƯỢC DUYỆT' ) !== false, $co['ghi_chu'] );
t( 'và chỉ đường sửa: xoá mẫu để lấy lại',
	strpos( $co['ghi_chu'], 'xoá mẫu' ) !== false, $co['ghi_chu'] );

/* 🔴 MỘT NGÀY MỘT CỜ. Người chấm vào và ra, có khi thêm ca tối — ba lượt lệch là ba cờ giống
   hệt nhau, và màn cờ thành bãi rác. */
$so_truoc = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'ghi_chu' ) );
VHCC_Mat::soi( $U, vec_cach( 0.1, 0.99 ), '2026-08-24', 'VIVO' );
VHCC_Mat::soi( $U, vec_cach( 0.1, 1.05 ), '2026-08-24', 'VIVO' );
t( 'lệch thêm hai lượt cùng ngày vẫn chỉ MỘT cờ',
	$so_truoc === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'ghi_chu' ) ) );
/* Ngày khác thì là cờ khác — đó là chuyện khác hẳn. */
VHCC_Mat::soi( $U, vec_cach( 0.1, 0.99 ), '2026-08-25', 'VIVO' );
t( 'ngày khác thì cờ khác',
	$so_truoc + 1 === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'ghi_chu' ) ) );

/* Lệch KHÔNG được đụng vào mẫu — nếu không thì người lạ chấm vài lần là mẫu trôi sang họ. */
t( 'lượt lệch KHÔNG gộp vào mẫu', 1 === (int) VHCC_Mat::mau( 'NV001' )['so_lan'] );

/* ============================================================ 5. KHÔNG BAO GIỜ CHẶN */
/* 🔴 Phương án B: gắn cờ, không gác cửa. Mọi đường ra của soi() đều phải ok. */
$moi_kieu = array(
	'dãy hỏng'        => VHCC_Mat::soi( $U, 'rác', '2026-08-26', 'VIVO' ),
	'dãy rỗng'        => VHCC_Mat::soi( $U, null, '2026-08-26', 'VIVO' ),
	'lệch hẳn'        => VHCC_Mat::soi( $U, vec_cach( 0.1, 2.0 ), '2026-08-26', 'VIVO' ),
	'không có mã NV'  => VHCC_Mat::soi( array( 'ma_nv' => '', 'ho_ten' => 'X' ), vec( 0.1 ), '2026-08-26', '' ),
);
foreach ( $moi_kieu as $ten_k => $kq_k ) {
	t( "\"$ten_k\": vẫn trả ok, không chặn ai", ! empty( $kq_k['ok'] ), $kq_k );
	t( "\"$ten_k\": không trả về thứ gì chặn được",
		! isset( $kq_k['chan'] ) && ! isset( $kq_k['error'] ), $kq_k );
}
t( 'dãy hỏng thì bỏ qua, KHÔNG lấy làm mẫu và KHÔNG gắn cờ',
	'vector_hong' === $moi_kieu['dãy hỏng']['bo_qua'] );
t( 'không có mã NV thì bỏ qua', 'khong_ma' === $moi_kieu['không có mã NV']['bo_qua'] );

/* Tắt tính năng ở Cài đặt thì im hẳn. */
update_option( 'vhcc_mat_bat', '0' );
$r = VHCC_Mat::soi( $U, vec_cach( 0.1, 2.0 ), '2026-08-27', 'VIVO' );
t( 'tắt ở Cài đặt thì không soi gì', 'tat' === $r['bo_qua'], $r );
update_option( 'vhcc_mat_bat', '1' );

/* Ngưỡng chỉnh được, nhưng số vô lý thì về mặc định — không để một ô gõ nhầm làm cả công ty
   bị gắn cờ, hay không ai bị gắn cờ nữa. */
update_option( 'vhcc_mat_nguong', '0.75' );
t( 'ngưỡng chỉnh được', abs( VHCC_Mat::nguong_lech() - 0.75 ) < 1e-9 );
update_option( 'vhcc_mat_nguong', '9' );
t( 'ngưỡng vô lý (9) về mặc định', abs( VHCC_Mat::nguong_lech() - VHCC_Mat::D_LECH ) < 1e-9 );
update_option( 'vhcc_mat_nguong', '0' );
t( 'ngưỡng 0 cũng về mặc định', abs( VHCC_Mat::nguong_lech() - VHCC_Mat::D_LECH ) < 1e-9 );
delete_option( 'vhcc_mat_nguong' );

/* ============================================================ 6. MẪU HỎNG THÌ LẤY LẠI */
$wpdb->update( VHCC_DB::t( 'mat_mau' ), array( 'vector' => 'hỏng rồi' ), array( 'ma_nv' => 'NV001' ) );
$r = VHCC_Mat::soi( $U, vec( 0.1 ), '2026-08-28', 'VIVO' );
t( 'mẫu trong kho hỏng -> lấy lại mẫu mới', 'lay_lai_mau' === $r['ket_qua'], $r );
t( 'và về lại trạng thái chờ duyệt', 'cho' === VHCC_Mat::mau( 'NV001' )['trang_thai'] );
t( 'ghi rõ vì sao phải lấy lại',
	strpos( VHCC_Mat::mau( 'NV001' )['ghi_chu'], 'không đọc được' ) !== false );

/* ============================================================ 7. TRẦN SỐ LẦN GỘP */
$wpdb->update( VHCC_DB::t( 'mat_mau' ),
	array( 'so_lan' => VHCC_Mat::GOP_TOI_DA ), array( 'ma_nv' => 'NV001' ) );
$r = VHCC_Mat::soi( $U, vec_cach( 0.1, 0.10 ), '2026-08-29', 'VIVO' );
t( 'đủ trần thì thôi gộp', empty( $r['gop'] ), $r );
t( 'nhưng vẫn kết luận khớp', 'khop' === $r['ket_qua'], $r );
t( 'số lần gộp không vượt trần',
	VHCC_Mat::GOP_TOI_DA === (int) VHCC_Mat::mau( 'NV001' )['so_lan'] );

/* ============================================================ 8. QUẢN TRỊ: DUYỆT / XOÁ */
$AD  = array( 'name' => 'Sếp',  'role' => 'Admin' );
$NV  = array( 'name' => 'Em',   'role' => 'Nhân viên' );
$CHT = array( 'name' => 'Anh',  'role' => 'Cửa hàng trưởng' );
$KT  = array( 'name' => 'Chị',  'role' => 'Kế toán cá nhân' );

t( 'Nhân viên KHÔNG xem được danh sách mẫu', 0 === count( VHCC_Mat::ds( $NV ) ) );
t( 'Cửa hàng trưởng cũng không', 0 === count( VHCC_Mat::ds( $CHT ) ) );
t( 'Kế toán xem được', count( VHCC_Mat::ds( $KT ) ) > 0 );
t( 'Admin xem được', count( VHCC_Mat::ds( $AD ) ) > 0 );

/* 🔴 KHÔNG TRẢ DÃY ĐẶC TRƯNG RA MÀN HÌNH. Nó là dữ liệu sinh trắc học, và màn hình không dùng
   tới nó — chỉ cần biết đã có mẫu, gộp mấy lần, duyệt chưa. */
$ds = VHCC_Mat::ds( $AD );
t( 'danh sách KHÔNG kèm dãy đặc trưng', ! isset( $ds[0]['vector'] ), $ds[0] );
t( 'nhưng có đủ thứ để quyết định',
	isset( $ds[0]['ma_nv'], $ds[0]['trang_thai'], $ds[0]['so_lan'], $ds[0]['nguon_ngay'] ) );

t( 'Nhân viên không duyệt được mẫu', empty( VHCC_Mat::duyet( $NV, 'NV001' )['ok'] ) );
t( 'Cửa hàng trưởng không duyệt được', empty( VHCC_Mat::duyet( $CHT, 'NV001' )['ok'] ) );
$r = VHCC_Mat::duyet( $KT, 'NV001' );
t( 'Kế toán duyệt được', ! empty( $r['ok'] ), $r );
t( 'trạng thái thành đã duyệt', 'duyet' === VHCC_Mat::mau( 'NV001' )['trang_thai'] );
t( 'ghi lại ai duyệt', 'Chị' === VHCC_Mat::mau( 'NV001' )['nguoi_duyet'] );
t( 'duyệt mã không có thì báo lỗi', empty( VHCC_Mat::duyet( $AD, 'KHONG_CO' )['ok'] ) );

/* Mẫu đã duyệt thì cờ KHÔNG còn câu cảnh báo "chưa duyệt" nữa. */
$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'ghi_chu' ) );
$r = VHCC_Mat::soi( $U, vec_cach( 0.1, 1.2 ), '2026-08-30', 'VIVO' );
$co2 = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . VHCC_DB::t( 'ghi_chu' )
	. ' WHERE flag_id=%s', $r['flagId'] ), ARRAY_A );
t( 'mẫu đã duyệt thì cờ không nhắc "chưa duyệt"',
	strpos( $co2['ghi_chu'], 'CHƯA ĐƯỢC DUYỆT' ) === false, $co2['ghi_chu'] );

/* Xoá mẫu là đường sửa cho tình huống mẫu đầu tiên bắt nhầm mặt người khác. */
t( 'Nhân viên không xoá được mẫu', empty( VHCC_Mat::xoa( $NV, 'NV001' )['ok'] ) );
$r = VHCC_Mat::xoa( $AD, 'NV001' );
t( 'Admin xoá được', ! empty( $r['ok'] ), $r );
t( 'và nói rõ chuyện gì xảy ra tiếp',
	strpos( $r['thong_bao'], 'tự lấy mẫu mới' ) !== false, $r );
t( 'mẫu biến mất thật', null === VHCC_Mat::mau( 'NV001' ) );
/* Xoá xong thì lượt tới tự lấy mẫu mới — đúng vòng đời. */
$r = VHCC_Mat::soi( $U, vec( 0.9 ), '2026-08-31', 'VIVO' );
t( 'lượt tới tự lấy mẫu mới', 'lay_mau' === $r['ket_qua'], $r );

/* ============================================================ 9. ĐẾM CHO MÀN QUẢN TRỊ */
/* Bốn mẫu: NV001 (vừa lấy lại) + ba mã riêng của mục 4. */
$d = VHCC_Mat::dem();
t( 'đếm được tổng số mẫu', 4 === $d['tong'], $d );
t( 'và số mẫu đang chờ duyệt', 4 === $d['cho'], $d );
VHCC_Mat::duyet( $AD, 'NV_GOP' );
$d = VHCC_Mat::dem();
t( 'duyệt một mẫu thì số chờ giảm', 3 === $d['cho'] && 4 === $d['tong'], $d );

/* Lọc theo trạng thái — màn quản trị mở ra là muốn thấy ngay cái đang chờ. */
t( 'lọc được mẫu đang chờ', 3 === count( VHCC_Mat::ds( $AD, 'cho' ) ) );
t( 'lọc được mẫu đã duyệt', 1 === count( VHCC_Mat::ds( $AD, 'duyet' ) ) );
t( 'không lọc thì thấy hết', 4 === count( VHCC_Mat::ds( $AD ) ) );

echo "\n";
if ( $truot ) {
	echo 'TRƯỢT ' . count( $truot ) . ":\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — đối chiếu khuôn mặt GẮN CỜ, không bao giờ chặn ai.\n";
