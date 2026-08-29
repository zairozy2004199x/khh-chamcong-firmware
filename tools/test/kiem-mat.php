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
/** So bằng nhau, và IN RA cái nhận được — "mong X, được Y" đọc nhanh hơn "sai". */
function teq( $ten, $mong, $duoc ) {
	t( $ten, $mong === $duoc, 'mong ' . var_export( $mong, true ) . ', được ' . var_export( $duoc, true ) );
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

/* ============================================================ 4b. CHẾ ĐỘ IM LẶNG (mặc định)
 *
 * 🔴 MẶC ĐỊNH KHÔNG GẮN CỜ, VÀ ĐÓ LÀ CHỦ Ý. Ngưỡng 0,60 là con số của ngành chứ không phải của
 * K&H — nó phụ thuộc ánh sáng từng cơ sở, camera từng đời máy, khẩu trang. Bật cờ ngay bằng số
 * mặc định dẫn tới một trong hai kết cục, cả hai đều hỏng: cả trăm cờ oan (hai tuần sau không
 * ai mở màn cờ nữa, cờ THẬT chìm theo), hoặc không cờ nào và tưởng mọi thứ sạch.
 */
teq( 'mặc định là chế độ IM LẶNG', 'im', VHCC_Mat::che_do() );
$U_IM = gieo_mau( 'NV_IM', 0.3 );
$truoc_im = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'ghi_chu' ) );
$r = VHCC_Mat::soi( $U_IM, vec_cach( 0.3, 0.95 ), '2026-08-23', 'VIVO' );
t( 'chế độ im: vẫn KẾT LUẬN lệch', 'lech' === $r['ket_qua'], $r );
t( 'và nói rõ đang chạy im', ! empty( $r['im'] ), $r );
t( 'nhưng KHÔNG gắn cờ nào',
	$truoc_im === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'ghi_chu' ) ) );
/* Nhưng PHẢI ghi nhật ký — đó chính là thứ để lát nữa chọn ngưỡng. */
$nk = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . VHCC_DB::t( 'mat_nhat_ky' )
	. ' WHERE ma_nv=%s AND ngay=%s', 'NV_IM', '2026-08-23' ), ARRAY_A );
t( 'nhưng CÓ ghi nhật ký', is_array( $nk ), $nk );
t( 'nhật ký ghi đúng kết quả', 'lech' === $nk['ket_qua'] );
t( 'và ghi rõ chưa gắn cờ', 0 === (int) $nk['co_gan'] );
t( 'nhật ký KHÔNG giữ dãy đặc trưng', ! isset( $nk['vector'] ) );

/* Phân bố phải có CẢ phần khớp, không chỉ cái đuôi lệch — chỉ có số của phần lệch thì không
   biết đám đông người thật nằm ở đâu, mà ngưỡng đúng nằm ở chỗ hai đám tách ra. */
VHCC_Mat::soi( $U_IM, vec_cach( 0.3, 0.10 ), '2026-08-24', 'VIVO' );
$tk = VHCC_Mat::thong_ke( array( 'role' => 'Admin', 'name' => 'Sếp' ) );
t( 'thống kê xem được', ! empty( $tk['ok'] ), $tk );
t( 'và có cả lượt khớp lẫn lượt lệch', count( $tk['o'] ) >= 2, $tk['o'] );
t( 'nhân viên KHÔNG xem được thống kê',
	empty( VHCC_Mat::thong_ke( array( 'role' => 'Nhân viên' ) )['ok'] ) );

/* ============================================================ 4c. BẬT CỜ THẬT */
update_option( 'vhcc_mat_che_do', 'co' );
teq( 'bật được chế độ gắn cờ', 'co', VHCC_Mat::che_do() );

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

/* ============================================================ 8b. MẪU TỪ ẢNH THẺ LÚC TẠO HỒ SƠ
 * Anh Thắng 29/08/2026: *"nếu chưa có máy thì ảnh chụp đó cũng dùng để xác định face qua chấm
 * công online"*. `dat_mau_tu_anh_the()` là đường nối: `VHCC_NhanSu::them_nv_cua_hang()` gọi nó
 * với dãy đặc trưng trình duyệt đã tính sẵn từ ảnh thẻ ngay lúc chụp (xem docblock của hàm và
 * script mới ở `VHCC_Web::khoi_them_nv()`).
 */
$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'mat_mau' ) . " WHERE ma_nv IN ('ATE01','ATE02')" );
t( '🔴 seed mẫu từ ảnh thẻ: chạy được', VHCC_Mat::dat_mau_tu_anh_the( 'ATE01', vec( 0.2 ) ) );
$mau_ate = VHCC_Mat::mau( 'ATE01' );
t( '🔴 mẫu vào thẳng "đã duyệt" — KHÁC mẫu tự lấy từ chấm công (luôn bắt đầu "chờ duyệt")',
	'duyet' === $mau_ate['trang_thai'], $mau_ate );
teq( 'số lần gộp bắt đầu từ 1', 1, (int) $mau_ate['so_lan'] );
t( 'ghi rõ nguồn gốc mẫu trong ghi chú, để người đọc "duyệt" sau này biết vì sao đã duyệt sẵn',
	strpos( $mau_ate['ghi_chu'], 'ảnh thẻ' ) !== false, $mau_ate );
/* Cách nhau CHƯA TỚI 1e-6 là do `luu_mau()` làm tròn 6 số lẻ trước khi lưu (xem hàm đó) — so
   bằng `===` như $mong/$duoc thường làm ở đây sẽ đỏ oan vì nhiễu số thực, không phải vì lưu sai. */
t( 'dãy đặc trưng lưu đúng như đưa vào (sai số làm tròn không đáng kể)',
	VHCC_Mat::khoang_cach( VHCC_Mat::doc_vector( vec( 0.2 ) ), VHCC_Mat::doc_vector( $mau_ate['vector'] ) ) < 1e-5 );

/* Có mẫu ảnh thẻ rồi thì lượt chấm công ONLINE đầu tiên của người đó SO NGAY với nó — khác hẳn
   người chưa từng có ảnh thẻ (mục 1 ở trên: người mới luôn 'lay_mau' ở lượt đầu). */
$U_ATE = array( 'ma_nv' => 'ATE01', 'ho_ten' => 'Ảnh Thẻ Trước', 'coso' => 'VIVO' );
$r = VHCC_Mat::soi( $U_ATE, vec( 0.2 ), '2026-09-01', 'VIVO' );
t( '🔴 lượt chấm công đầu tiên SO NGAY với ảnh thẻ, không lấy chính lượt đó làm mẫu mới',
	'lay_mau' !== $r['ket_qua'], $r );
t( 'khớp mẫu ảnh thẻ vì cùng khuôn mặt', 'khop' === $r['ket_qua'], $r );

/* Vector hỏng thì bỏ qua, không âm thầm ghi rác vào kho — cùng chốt `doc_vector()` đã canh. */
t( 'vector hỏng thì KHÔNG seed', false === VHCC_Mat::dat_mau_tu_anh_the( 'ATE02', 'rác' ) );
t( 'và không để lại mẫu rác', null === VHCC_Mat::mau( 'ATE02' ) );
t( 'thiếu mã NV thì cũng không seed', false === VHCC_Mat::dat_mau_tu_anh_the( '', vec( 0.2 ) ) );

/* Đã có mẫu rồi thì KHÔNG ghi đè — chốt phòng thủ; đường gọi hiện tại (mã tạm luôn mới toanh)
   chưa bao giờ chạm chốt này, nhưng hàm không được âm thầm phá mẫu đã trưởng thành nếu mai này
   có đường gọi khác dùng lại mã cũ. */
t( '🔴 đã có mẫu rồi thì không seed đè lên',
	false === VHCC_Mat::dat_mau_tu_anh_the( 'ATE01', vec( 0.9 ) ) );
t( 'mẫu cũ vẫn giữ nguyên, không bị đè',
	VHCC_Mat::khoang_cach( VHCC_Mat::doc_vector( vec( 0.2 ) ),
		VHCC_Mat::doc_vector( VHCC_Mat::mau( 'ATE01' )['vector'] ) ) < 1e-5 );

/* ============================================================ 9. ĐẾM CHO MÀN QUẢN TRỊ */
/* Đếm theo SỐ THẬT trong kho, không gõ tay một con số rồi sửa mỗi lần thêm mục thử mới —
   đó là kiểu phép thử vỡ vì lý do chẳng liên quan gì tới thứ nó canh. */
$tong_that = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'mat_mau' ) );
$cho_that  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . VHCC_DB::t( 'mat_mau' ) . " WHERE trang_thai='cho'" );
$d = VHCC_Mat::dem();
teq( 'đếm đúng tổng số mẫu', $tong_that, $d['tong'] );
teq( 'đếm đúng số mẫu đang chờ duyệt', $cho_that, $d['cho'] );
VHCC_Mat::duyet( $AD, 'NV_GOP' );
$d = VHCC_Mat::dem();
teq( 'duyệt một mẫu thì số chờ giảm đúng một', $cho_that - 1, $d['cho'] );
teq( 'tổng không đổi', $tong_that, $d['tong'] );

/* Lọc theo trạng thái — màn quản trị mở ra là muốn thấy ngay cái đang chờ. */
teq( 'lọc được mẫu đang chờ', $cho_that - 1, count( VHCC_Mat::ds( $AD, 'cho' ) ) );
teq( 'lọc được mẫu đã duyệt', $tong_that - ( $cho_that - 1 ), count( VHCC_Mat::ds( $AD, 'duyet' ) ) );
teq( 'không lọc thì thấy hết', $tong_that, count( VHCC_Mat::ds( $AD ) ) );

/* ============================================ 10. THƯ VIỆN ĐẶT TRONG PLUGIN
 *
 * Anh Thắng chọn: *"Anh tải file rồi bỏ vào thư mục plugin"*. Đúng hơn hẳn gọi CDN — trang
 * chấm công không phụ thuộc một địa chỉ ngoài, model tải từ chính hosting của mình, và không
 * gửi thông tin lượt truy cập của nhân viên sang máy chủ bên thứ ba.
 *
 * 🔴 THIẾU FILE LÀ CHUYỆN BÌNH THƯỜNG, KHÔNG PHẢI LỖI. Chưa bỏ file vào thì tính năng im lặng
 *    không chạy, mọi thứ khác nguyên vẹn.
 */
/* 🔴 THƯ VIỆN PHẢI NẰM NGOÀI THƯ MỤC PLUGIN.
   Anh Thắng 25/08/2026: *"sao tải xong về nó lại mất tiêu rồi"* — vì bản trước để ở
   `plugins/vhcp-cham-cong/assets/mat/`, mà cài đè plugin bằng tệp .zip thì WordPress XOÁ SẠCH
   thư mục plugin cũ rồi giải nén bản mới đè lên. Bảy megabyte vừa tải bay theo, và lần cập
   nhật nào cũng bay lại. Ghi cảnh báo trong tài liệu mà vẫn để cái bẫy nằm đó thì không phải
   là sửa — chỗ đúng là `uploads/`, nơi WordPress không đụng tới khi cập nhật plugin. */
$tm = VHCC_Mat::thu_muc();
t( 'thư mục nằm trong uploads, KHÔNG trong plugin',
	false !== strpos( $tm, 'uploads' ) && false === strpos( $tm, 'plugins' ), $tm );
t( 'chỗ cũ trong plugin vẫn còn được nhận',
	false !== strpos( VHCC_Mat::thu_muc_cu(), 'assets/mat' ), VHCC_Mat::thu_muc_cu() );
wp_mkdir_p( $tm );

$tv = VHCC_Mat::thu_vien();
t( 'chưa bỏ file vào thì báo CHƯA CÓ', empty( $tv['co'] ), $tv );
t( 'và liệt kê ra còn thiếu file nào', count( $tv['thieu'] ) > 0 );
t( 'thiếu đủ 8 file (1 thư viện + 7 model)', 8 === count( $tv['thieu'] ), $tv['thieu'] );
t( 'có tên tệp thư viện', in_array( 'face-api.min.js', $tv['thieu'], true ) );
t( 'có tên các model nhận dạng',
	in_array( 'face_recognition_model-shard1', $tv['thieu'], true )
	&& in_array( 'face_recognition_model-shard2', $tv['thieu'], true ) );
t( 'có model dò mặt bản nhẹ',
	in_array( 'tiny_face_detector_model-shard1', $tv['thieu'], true ) );

/* Bỏ đủ file giả vào -> phải báo SẴN SÀNG. Không có phép thử này thì nhánh "đủ file" chưa
   bao giờ chạy, và ngày anh Thắng bỏ file thật vào mới biết nó hỏng. */
$bo = VHCC_Mat::bo_file();
$da_tao = array();
foreach ( array_merge( array( $bo['goc']['js'] ), $bo['goc']['mau'] ) as $f_gia ) {
	file_put_contents( $tm . $f_gia, '/* tệp giả của bài kiểm */' );
	$da_tao[] = $tm . $f_gia;
}
$tv2 = VHCC_Mat::thu_vien();
t( 'bỏ đủ file thì báo SẴN SÀNG', ! empty( $tv2['co'] ), $tv2 );
t( 'không còn thiếu gì', 0 === count( $tv2['thieu'] ) );
t( 'trả về địa chỉ tệp thư viện', false !== strpos( $tv2['js'], 'face-api.min.js' ), $tv2['js'] );
t( 'và địa chỉ thư mục model', false !== strpos( $tv2['mau_url'], 'vhcc-mat' ), $tv2['mau_url'] );

/* Thiếu ĐÚNG MỘT file cũng phải là "chưa sẵn sàng" — nạp thư viện mà thiếu một model thì nó
   chết giữa chừng, và chết ở trình duyệt của nhân viên chứ không ở đây. */
unlink( $tm . 'face_recognition_model-shard2' );
$tv3 = VHCC_Mat::thu_vien();
t( 'thiếu một file thôi cũng là chưa sẵn sàng', empty( $tv3['co'] ) );
t( 'và nói đúng file nào thiếu',
	array( 'face_recognition_model-shard2' ) === $tv3['thieu'], $tv3['thieu'] );

/* Tệp còn ở CHỖ CŨ (trong plugin) thì vẫn chạy được — ai đã kịp bỏ vào đó không phải làm lại.
   Nhưng `tai_ve()` phải nhận ra chỗ mới đang thiếu và CHÉP sang, chứ không báo "đủ rồi". */
foreach ( $da_tao as $f_xoa ) { if ( is_file( $f_xoa ) ) { unlink( $f_xoa ); } }
$tmc = VHCC_Mat::thu_muc_cu();
wp_mkdir_p( $tmc );
$da_cu = array();
foreach ( array_merge( array( $bo['goc']['js'] ), $bo['goc']['mau'] ) as $f_gia ) {
	$noi_dung = ( '.json' === substr( $f_gia, -5 ) )
		? wp_json_encode( array( 'weights' => array_fill( 0, 60, 'x' ) ) )
		: ( ( '.js' === substr( $f_gia, -3 ) ) ? 'var faceapi=' . str_repeat( 'a', 60000 )
			: str_repeat( "\x7f\x00\x11\x22", 5000 ) );
	file_put_contents( $tmc . $f_gia, $noi_dung );
	$da_cu[] = $tmc . $f_gia;
}
$tv_cu = VHCC_Mat::thu_vien();
t( 'tệp ở chỗ cũ vẫn dùng được', ! empty( $tv_cu['co'] ), $tv_cu );
t( 'và biết mình đang đọc ở chỗ cũ', false !== strpos( $tv_cu['noi'], 'assets/mat' ), $tv_cu['noi'] );

$r_doi = VHCC_Mat::tai_ve( array( 'name' => 'Sếp', 'role' => 'Admin' ) );
t( 'dời sang chỗ an toàn: chép chứ không tải lại 7 MB',
	! empty( $r_doi['ok'] ) && 8 === (int) $r_doi['tai'], $r_doi );
t( 'và chép thật sang uploads', is_readable( $tm . 'face-api.min.js' ) );
$tv_moi = VHCC_Mat::thu_vien();
t( 'sau khi dời thì đọc ở chỗ mới',
	! empty( $tv_moi['co'] ) && false !== strpos( $tv_moi['noi'], 'uploads' ), $tv_moi['noi'] );

/* Xoá phải xoá CẢ HAI nơi — sót một bản ở chỗ cũ thì `thu_vien()` vẫn thấy "đủ", và người bấm
   "xoá để tải lại" không hiểu vì sao chẳng có gì đổi. */
$r_xoa_tv = VHCC_Mat::xoa_thu_vien( array( 'name' => 'Sếp', 'role' => 'Admin' ) );
t( 'xoá thư viện xoá cả hai nơi', 16 === (int) $r_xoa_tv['so'], $r_xoa_tv );
t( 'chỗ mới sạch', ! is_file( $tm . 'face-api.min.js' ) );
t( 'chỗ cũ cũng sạch', ! is_file( $tmc . 'face-api.min.js' ) );
t( 'và quay về báo thiếu', empty( VHCC_Mat::thu_vien()['co'] ) );

/* ---- Trang trạm: máy chủ gác, không để trình duyệt tự dò ---- */
$src_tram = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-tram.php' );
t( 'trang trạm hỏi máy chủ về thư viện',
	false !== strpos( $src_tram, 'VHCC_Mat::thu_vien()' ) );
t( 'và chỉ bật khi CẢ HAI: bật ở cài đặt VÀ đủ file',
	false !== strpos( $src_tram, "VHCC_Mat::bat() && \$tv['co']" ), $src_tram );

$src_tp = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/templates/tram.php' );
t( 'đối chiếu chạy SAU khi giờ đã ghi', false !== strpos( $src_tp, 'soiMat(anhVuaGui' ) );
t( 'giữ lại ảnh trước khi xoá để còn soi', false !== strpos( $src_tp, 'var anhVuaGui = ANH;' ) );
t( 'thư viện chỉ nạp MỘT lần cho cả phiên', false !== strpos( $src_tp, 'if(MAT_TAI) return MAT_TAI;' ) );
t( 'không thấy mặt trong ảnh thì KHÔNG gửi gì',
	false !== strpos( $src_tp, 'if(!kq || !kq.descriptor) return;' ) );
/* 🔴 Hỏng ở đâu cũng IM. Người dùng không được thấy lỗi của một thứ họ không yêu cầu và không
   sửa được — cái duy nhất họ cần thấy là dòng "đã ghi giờ vào". */
t( 'mọi lỗi của phần soi mặt đều nuốt im',
	preg_match( "/soiMat[\\s\\S]{0,2500}catch\\(function\\(\\)\\{ \\/\\* im/", $src_tp ) === 1 );
t( 'KHÔNG có địa chỉ CDN nào trong trang',
	false === strpos( $src_tp, 'cdn.' ) && false === strpos( $src_tp, 'unpkg' ) );

/* ============================================ 11. NÚT TẢI THƯ VIỆN HỘ
 *
 * Bắt anh Thắng lọc 8 tệp trong một kho mã nguồn rồi tải lên bằng File Manager là tám lần có
 * thể tải nhầm tệp, nhầm thư mục, hoặc bỏ sót. Máy chủ tự tải thì chỉ còn một cái nút.
 */
$src_mat = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-mat.php' );

/* 🔴 GHIM VÀO MỘT PHIÊN BẢN, KHÔNG DÙNG `master`. Nhánh master đổi lúc nào không ai báo, và
   một bản model mới có thể không đọc được bằng dãy đặc trưng cũ — nghĩa là mọi mẫu khuôn mặt
   đã lấy thành vô nghĩa, mà không có gì đỏ lên cả. */
t( 'nguồn tải ghim vào một phiên bản', preg_match( "#face-api\.js/v[\d.]+/#", VHCC_Mat::NGUON ) === 1, VHCC_Mat::NGUON );
t( 'KHÔNG tải từ nhánh master', false === strpos( VHCC_Mat::NGUON, '/master/' ), VHCC_Mat::NGUON );

/* Chỉ Admin: đây là lệnh bảo máy chủ đi tải tệp từ internet về thư mục mã nguồn. */
t( 'Kế toán KHÔNG tải được', empty( VHCC_Mat::tai_ve( array( 'role' => 'Kế toán cá nhân' ) )['ok'] ) );
t( 'Quản lý KHÔNG tải được', empty( VHCC_Mat::tai_ve( array( 'role' => 'Quản lý' ) )['ok'] ) );
t( 'Nhân viên KHÔNG tải được', empty( VHCC_Mat::tai_ve( array( 'role' => 'Nhân viên' ) )['ok'] ) );
t( 'Kế toán KHÔNG xoá được thư viện',
	empty( VHCC_Mat::xoa_thu_vien( array( 'role' => 'Kế toán cá nhân' ) )['ok'] ) );

/* 🔴 Chỉ nhận tên NẰM TRONG danh sách khai sẵn — ghép tên từ ngoài vào đường dẫn là mở đường
   cho `../` đi ngược lên thư mục khác. */
$tai_mot = new ReflectionMethod( 'VHCC_Mat', 'tai_mot' );
$tai_mot->setAccessible( true );
foreach ( array( '../../wp-config.php', 'bat-ky-gi.js', '', 'face-api.min.js.bak' ) as $ten_la ) {
	$kq_la = $tai_mot->invoke( null, $ten_la, VHCC_Mat::thu_muc() );
	t( "tên lạ \"$ten_la\" bị chối trước khi đi tải",
		is_string( $kq_la ) && false !== strpos( $kq_la, 'không nằm trong danh sách' ), $kq_la );
}

/* 🔴 KHÔNG TIN MÃ 200. Nhà mạng chèn trang quảng cáo, GitHub trả trang lỗi, tường lửa trả
   trang đăng nhập — tất cả đều kèm mã 200. Ghi một trang HTML dưới tên model thì mọi thứ trông
   như đã cài xong, và hỏng ở đúng chỗ không ai soi: trình duyệt của nhân viên. */
$xet = new ReflectionMethod( 'VHCC_Mat', 'xet_tep' );
$xet->setAccessible( true );
$html = '<!DOCTYPE html><html><body>' . str_repeat( 'trang loi ', 200 ) . '</body></html>';
t( 'trang HTML dưới tên model bị chối',
	true !== $xet->invoke( null, 'face_recognition_model-shard1', $html ) );
t( 'trang HTML dưới tên .json bị chối',
	true !== $xet->invoke( null, 'face_recognition_model-weights_manifest.json', $html ) );
t( 'tệp .js không có mã face-api bị chối',
	true !== $xet->invoke( null, 'face-api.min.js', str_repeat( 'var x = 1; ', 200 ) ) );
t( 'tệp quá nhỏ bị chối (thường là trang lỗi)',
	true !== $xet->invoke( null, 'face_recognition_model-shard1', 'oops' ) );
t( 'model quá nhỏ bị chối', true !== $xet->invoke( null, 'face_recognition_model-shard1',
	str_repeat( "\x01\x02", 500 ) ) );
/* Và nhận đúng thứ thật. */
t( 'JSON thật được nhận',
	true === $xet->invoke( null, 'tiny_face_detector_model-weights_manifest.json',
		wp_json_encode( array( 'weights' => array_fill( 0, 60, 'x' ) ) ) ) );
/* Thư viện thật ~250 KB; ngưỡng 50 KB bắt được trang lỗi mà không chối nhầm tệp thật. */
t( 'mã face-api thật được nhận',
	true === $xet->invoke( null, 'face-api.min.js', 'var faceapi=' . str_repeat( 'a', 60000 ) ) );
t( 'tệp .js nhỏ hơn 50 KB bị chối, dù có chữ faceapi',
	true !== $xet->invoke( null, 'face-api.min.js', 'var faceapi=' . str_repeat( 'a', 600 ) ) );
t( 'model nhị phân thật được nhận',
	true === $xet->invoke( null, 'face_recognition_model-shard1', str_repeat( "\x7f\x00\x11\x22", 5000 ) ) );

/* ⚠️ Ghi ra tệp TẠM rồi mới đổi tên. Ghi thẳng vào tên thật mà đứt giữa chừng là để lại một
   tệp hỏng dở, và lần sau `is_readable()` thấy có tệp nên báo "đủ rồi". */
t( 'ghi tệp tạm rồi đổi tên', false !== strpos( $src_mat, "'.dang-tai'" )
	&& false !== strpos( $src_mat, 'rename( $tam' ) );
/* ⚠️ Tải từng đợt, canh đồng hồ — hosting cắt lượt PHP ở 30 giây mà một model đã 4 MB. */
t( 'canh giờ để không bị cắt giữa chừng',
	false !== strpos( $src_mat, "ini_get( 'max_execution_time' )" )
	&& false !== strpos( $src_mat, 'time() > $het' ) );

/* Nút trong màn Cài đặt. */
$src_ad = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-admin.php' );
t( 'có nút để máy chủ tự tải', false !== strpos( $src_ad, "value=\"tai_mat\"" ) );
t( 'có nút xoá và tải lại', false !== strpos( $src_ad, "value=\"xoa_mat_tv\"" ) );
/* 🔴 Thẻ <form> phải nằm NGOÀI form Cài đặt: HTML không cho lồng <form>, trình duyệt lặng lẽ
   vứt thẻ con rồi gộp ô nhập vào form cha — mỗi lần Lưu cài đặt là chạy kèm một lượt tải. */
t( 'form nút gom vào $form_roi, không lồng trong form Cài đặt',
	false !== strpos( $src_ad, "\$form_roi .= '<form method=\"post\" id=\"vhcc-f-taimat\">'" ) );
t( 'và có nonce riêng cho từng việc',
	false !== strpos( $src_ad, "wp_nonce_field( 'vhcc_tai_mat'" )
	&& false !== strpos( $src_ad, "wp_nonce_field( 'vhcc_xoa_mat_tv'" ) );
/* Vẫn giữ hướng dẫn tải tay: hosting chặn máy chủ ra internet là chuyện có thật. */
t( 'vẫn giữ hướng dẫn tải tay', false !== strpos( $src_ad, 'File Manager' ) );
t( 'và nói rõ nếu hosting chặn thì dùng cách tay',
	false !== strpos( $src_ad, 'chặn máy chủ ra internet' ) );

/* ============================================ 12. MÀN "KHUÔN MẶT" TRONG WP-ADMIN
 *
 * 🔴 KHÔNG CÓ MÀN NÀY THÌ CẢ TÍNH NĂNG VÔ DỤNG. Hệ thống mặc định chạy im — so, ghi số, không
 * gắn cờ — với ý là vài tuần sau đọc số rồi mới chọn ngưỡng. Mà không có chỗ đọc thì nó im mãi
 * mãi, và mọi thứ đã làm chỉ là một bảng dữ liệu không ai mở. Suýt nữa đúng như vậy: `thong_ke()`
 * viết xong rồi mà không màn nào gọi tới.
 */
$GLOBALS['VHCP_CO_QUYEN'] = true;
$_GET = array(); $_POST = array();
/* Mục 4c bật chế độ gắn cờ để thử cờ — trả về mặc định trước khi soi màn hình, không thì phép
   thử này đo đúng cái mục kia vừa đặt chứ không đo mặc định. */
update_option( 'vhcc_mat_che_do', 'im' );
ob_start(); VHCC_Man::trang_mat(); $h_mat = ob_get_clean();

t( 'màn vẽ được', strlen( $h_mat ) > 500 );
t( 'nhắc đang chạy IM', strpos( $h_mat, 'Đang chạy IM' ) !== false, substr( $h_mat, 0, 400 ) );
t( 'và chỉ đường sang Cài đặt để bật cờ', strpos( $h_mat, 'page=vhcc' ) !== false );
t( 'có bảng phân bố', strpos( $h_mat, 'phân bố' ) !== false );
t( 'nói rõ ngưỡng nằm ở chỗ hai đám tách nhau',
	strpos( $h_mat, 'tách khỏi cái đuôi' ) !== false );
t( 'có danh sách lượt lệch nhất để mở ảnh ra xem',
	strpos( $h_mat, 'lệch nhất' ) !== false );
t( 'có bảng mẫu khuôn mặt', strpos( $h_mat, 'Mẫu khuôn mặt' ) !== false );
t( 'cảnh báo mẫu chưa duyệt vẫn được dùng để so',
	strpos( $h_mat, 'vẫn được dùng để '  ) !== false );
t( 'và nói rõ hậu quả nếu mẫu bắt nhầm mặt', strpos( $h_mat, 'ngược' ) !== false );

/* 🔴 MỖI DÒNG MỘT <form> RIÊNG. Gộp cả bảng vào một form thì mọi ô ẩn `ma_nv` cùng được gửi
   lên, và máy chủ đọc cái CUỐI — bấm "Xoá mẫu" ở dòng đầu lại xoá mẫu của dòng cuối. */
$so_form = substr_count( $h_mat, '<form method="post"' );
$so_ma   = substr_count( $h_mat, 'name="ma_nv"' );
t( 'mỗi dòng mẫu có form riêng', $so_form >= $so_ma && $so_ma > 0, "form=$so_form ma_nv=$so_ma" );
t( 'xoá mẫu có hỏi lại', strpos( $h_mat, 'confirm(' ) !== false );

/* Bật chế độ gắn cờ thì màn phải NÓI KHÁC — người mở màn cần biết ngay đang ở chế độ nào. */
update_option( 'vhcc_mat_che_do', 'co' );
ob_start(); VHCC_Man::trang_mat(); $h_mat2 = ob_get_clean();
t( 'chế độ gắn cờ thì báo khác hẳn', strpos( $h_mat2, 'Đang GẮN CỜ THẬT' ) !== false );
t( 'và in ra ngưỡng đang dùng', strpos( $h_mat2, (string) VHCC_Mat::nguong_lech() ) !== false );
update_option( 'vhcc_mat_che_do', 'im' );

/* Duyệt / xoá qua màn hình, không phải gọi thẳng hàm. */
$ma_thu = VHCC_Mat::ds( array( 'role' => 'Admin' ), 'cho' );
if ( $ma_thu ) {
	$ma_1 = $ma_thu[0]['ma_nv'];
	$_POST = array( 'vhcc_mat_viec' => 'duyet', 'ma_nv' => $ma_1 );
	ob_start(); VHCC_Man::trang_mat(); ob_end_clean();
	t( 'bấm Duyệt trên màn thì mẫu đổi trạng thái',
		'duyet' === VHCC_Mat::mau( $ma_1 )['trang_thai'], $ma_1 );
	$_POST = array( 'vhcc_mat_viec' => 'xoa', 'ma_nv' => $ma_1 );
	ob_start(); VHCC_Man::trang_mat(); ob_end_clean();
	t( 'bấm Xoá trên màn thì mẫu biến mất', null === VHCC_Mat::mau( $ma_1 ) );
}
$_POST = array(); $_GET = array();

/* Menu phải khai màn này, kèm số chờ duyệt — mẫu chưa duyệt nằm im trong một tab không ai mở
   thì nó vẫn được dùng để so, và nếu chính tấm mẫu ấy bắt nhầm mặt thì hệ thống gắn cờ ngược
   suốt mà không ai biết. */
$GLOBALS['VHCP_MENU'] = array();
VHCC_Admin::menu();
t( 'menu có màn Khuôn mặt', isset( $GLOBALS['VHCP_MENU']['vhcc-mat'] ),
	array_keys( (array) $GLOBALS['VHCP_MENU'] ) );
t( 'và hàm vẽ gọi được',
	isset( $GLOBALS['VHCP_MENU']['vhcc-mat'] ) && is_callable( $GLOBALS['VHCP_MENU']['vhcc-mat']['cb'] ) );
$src_man = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-man.php' );
t( 'nhãn menu hiện số mẫu chờ duyệt', false !== strpos( $src_man, 'pending-count' )
	&& false !== strpos( $src_man, "VHCC_Mat::dem()['cho']" ) );

echo "\n";
if ( $truot ) {
	echo 'TRƯỢT ' . count( $truot ) . ":\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — đối chiếu khuôn mặt GẮN CỜ, không bao giờ chặn ai.\n";
