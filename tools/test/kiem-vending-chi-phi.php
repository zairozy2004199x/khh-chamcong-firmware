<?php
/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * KÉO CHI PHÍ VENDING HCMC VỀ THÀNH ĐƠN THẬT — `VHCP_Vending`.
 * Anh Thắng 25/09/2026: "nối chi phí từ web khác qua chi phí của web anh" → nhập thành đơn thật ·
 * khối "Vending", mỗi Bộ phận = một cơ sở · rồi chốt chiều: "Đẩy là đẩy từ vending về web chi phí của anh
 * để quyết toán" · "web đó nằm bên server khác" → web Vending POST sang, đơn về "Chờ quyết toán".
 * Chạy: php tools/test/kiem-vending-chi-phi.php
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );
vhcp_test_dat_gio( '2026-09-25 16:00:00' );
VHCP_Auth::dat_vai_tro( 'Admin', 'Anh Thắng' );
$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) { global $DAT, $TRUOT; if ( $ok ) { $DAT++; return; } $TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) ) : '' ); }
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . json_encode( $mong, JSON_UNESCAPED_UNICODE ) . ')', $mong === $thuc, $thuc ); }

VHCP_Cfg::save_config( array(
	'coso' => array( array( 'ten' => 'FUNZONE AN LẠC', 'maDonVi' => 'FZAL', 'phanLoaiLon' => 'KVC MN', 'tenMisa' => 'FZ' ) ),
	'users' => array( array( 'ten' => 'Admin', 'pin' => '1111', 'vaiTro' => 'Admin' ), array( 'ten' => 'Kế Toán A', 'pin' => '2222', 'vaiTro' => 'Kế toán cá nhân' ) ),
) );
VHCP_Cfg::clear_cache();

/* ── 0. Khối 'vending' có trong từ điển ────────────────────────────────────────────────────── */
teq( '🔴 khoi_cua("VENDING") = vending', 'vending', VHCP_DonVi::khoi_cua( 'VENDING' ) );
teq( '   ten_khoi(vending) = Vending', 'Vending', VHCP_Cfg::ten_khoi( 'vending' ) );
t( '   khoi_ds() liệt kê vending', in_array( 'vending', array_column( VHCP_DonVi::khoi_ds(), 'ma' ), true ), VHCP_DonVi::khoi_ds() );

/* ── 1. Cấu hình ───────────────────────────────────────────────────────────────────────────── */
$ch = VHCP_Vending::cau_hinh();
t( '🔴 gói màn không có khoá; san=false khi chưa khai; có địa chỉ nhận', ! array_key_exists( 'khoa', $ch ) && false === $ch['san'] && 0 === $ch['soDaNhap'] && false !== strpos( $ch['diaChiNhan'], '/wp-json/vhcp/v1/vending-nhan' ), $ch );
VHCP_Auth::dat_vai_tro( 'Kế toán cá nhân', 'Kế Toán A' );
$r = VHCP_Cfg::save_config( array( 'vending' => array( 'url' => 'https://vending.kh.vn', 'khoa' => 'KHOA-VENDING-1234567890' ) ) );
t( '🔴 Kế toán không đổi được kết nối', empty( $r['success'] ), $r );
VHCP_Auth::dat_vai_tro( 'Admin', 'Anh Thắng' );
$r = VHCP_Cfg::save_config( array( 'vending' => array( 'url' => 'https://vending.kh.vn/?vending_hcmc=1', 'khoa' => 'KHOA-VENDING-1234567890' ) ) );
t( '   Admin lưu được', ! empty( $r['success'] ), $r );
teq( '🔴 dán địa chỉ mở app (?vending_hcmc=1) → lưu về GỐC web (ảnh toast "trả lời không hiểu được: HTTP 200")', 'https://vending.kh.vn', get_option( 'vhcp_vd_url' ) );
teq( '   dán cả /wp-admin/admin.php?page=… → cũng về gốc', 'https://vending.kh.vn', VHCP_Vending::goc_dia_chi( 'https://vending.kh.vn/wp-admin/admin.php?page=vhcm' ) );
teq( '   web đặt trong thư mục con thì GIỮ thư mục', 'https://kh.vn/vending', VHCP_Vending::goc_dia_chi( 'https://kh.vn/vending/?vending_hcmc=1' ) );
VHCP_Cfg::save_config( array( 'vending' => array( 'url' => 'https://vending.kh.vn', 'khoa' => '' ) ) );
teq( '🔴 khoá rỗng = GIỮ', 'KHOA-VENDING-1234567890', get_option( 'vhcp_vd_khoa' ) );
teq( '   san=true (có khoá là nhận đẩy được)', true, VHCP_Vending::cau_hinh()['san'] );

/* ── 2. Gọi sang web: khoá trong header, không nằm trong địa chỉ ──────────────────────────── */
$KHO = array(
	array( 'id' => 1001, 'code' => 'CP-2026-0001', 'date' => '2026-09-03', 'department' => 'POSH', 'type' => 'Xăng xe', 'content' => 'Đổ xăng đi nạp máy', 'amount' => 350000, 'requester' => 'Nguyễn Văn Kỹ', 'approver' => 'Trần Quản Lý', 'status' => 'Đã thanh toán', 'receiptCode' => 'HD001', 'receiptLink' => 'https://vending.kh.vn/wp-content/uploads/hd001.jpg', 'note' => 'tuần 1', 'ownerRole' => 'tech_main' ),
	array( 'id' => 1002, 'code' => 'CP-2026-0002', 'date' => '2026-09-10', 'department' => 'JP', 'type' => 'Bảo trì', 'content' => 'Thay bo mạch', 'amount' => 2200000, 'requester' => 'Nguyễn Văn Kỹ', 'approver' => 'Trần Quản Lý', 'status' => 'Đã quyết toán', 'receiptCode' => '', 'receiptLink' => '', 'note' => '' ),
	array( 'id' => 1003, 'code' => 'CP-2026-0003', 'date' => '2026-09-12', 'department' => 'Pinball', 'type' => 'Vật tư', 'content' => 'Bóng đèn', 'amount' => 120000, 'requester' => 'Lê Thị Thu', 'approver' => '', 'status' => 'Chờ duyệt' ),
	array( 'id' => 1004, 'code' => 'CP-2026-0004', 'date' => '2026-09-15', 'department' => 'Thị trường', 'type' => 'Tiếp khách', 'content' => 'Cafe với mall', 'amount' => 500000, 'requester' => 'Lê Thị Thu', 'approver' => 'Trần Quản Lý', 'status' => 'Đã duyệt' ),
	array( 'id' => 1005, 'code' => 'CP-2026-0005', 'date' => '2026-09-18', 'department' => 'Kho lạ', 'type' => 'Khác', 'content' => 'x', 'amount' => 10000, 'requester' => 'A', 'approver' => 'B', 'status' => 'Đã thanh toán' ),
	array( 'id' => 1006, 'code' => 'CP-2026-0006', 'date' => '2026-09-20', 'department' => 'Vận hành chung', 'type' => 'Taxi / di chuyển', 'content' => 'Grab đi mall', 'amount' => 90000, 'requester' => 'Nguyễn Văn Kỹ', 'approver' => 'Trần Quản Lý', 'status' => 'Từ chối' ),
);
$json = function ( $kho ) { return json_encode( array( 'ok' => true, 'web' => 'VENDING HCMC', 'tu' => '2026-09-01', 'den' => '2026-09-30', 'soKhoan' => count( $kho ), 'chiPhi' => $kho ), JSON_UNESCAPED_UNICODE ); };
$GLOBALS['VHCP_HTTP'] = array( 'vending.kh.vn/wp-json/vending-hcmc/v1/chi-phi' => array( 'code' => 200, 'body' => $json( $KHO ) ) );
$GLOBALS['VHCP_DA_GET'] = array(); $GLOBALS['VHCP_DA_GET_ARGS'] = array();
$kt = VHCP_Vending::kiem_tra();
t( '   Kiểm tra kết nối: 6 khoản, đếm theo trạng thái', ! empty( $kt['success'] ) && 6 === $kt['soKhoan'] && 2 === $kt['theoTrangThai']['Đã thanh toán'], $kt );
t( '🔴 khoá đi trong header, KHÔNG trong địa chỉ', 'KHOA-VENDING-1234567890' === $GLOBALS['VHCP_DA_GET_ARGS'][0]['headers']['X-KHH-Khoa'] && false === strpos( implode( ' ', $GLOBALS['VHCP_DA_GET'] ), 'KHOA-VENDING' ), $GLOBALS['VHCP_DA_GET'] );

/* ── 3. Đồng bộ tháng 9 ────────────────────────────────────────────────────────────────────── */
$r = VHCP_Vending::dong_bo( array( 'thang' => '2026-09' ) );
t( '   dong_bo chạy', ! empty( $r['success'] ), $r );
teq( '🔴 mới 3 (Đã thanh toán · Đã quyết toán · Đã duyệt) · bỏ qua 2 (Chờ duyệt, Từ chối) · bộ phận lạ 1', array( 3, 0, 2, 1, 0 ), array( $r['moi'], $r['capNhat'], $r['boQua'], $r['boQuaBoPhan'], $r['daChot'] ) );
teq( '🔴 gieo 5 cơ sở + 10 loại lần đầu', array( 'coso' => 5, 'loai' => 10 ), $r['gieo'] );
t( '   lỗi chỉ ghi khoản bộ phận lạ, nêu mã', 1 === count( $r['loi'] ) && false !== mb_strpos( $r['loi'][0], 'CP-2026-0005' ) && false !== mb_strpos( $r['loi'][0], 'Kho lạ' ), $r['loi'] );
$map = VHCP_Meta::get_json( 'vending_map', array() );
teq( '   bản đồ id → mã đơn có 3 khoá', array( '1001', '1002', '1004' ), array_map( 'strval', array_keys( $map ) ) );
$m1 = $map['1001']; $d1 = VHCP_Don::don_row( $m1 ); $g1 = VHCP_Don::get_don( $m1, false );
teq( '🔴 đơn 1001: đơn vị VENDING · khối vending · luồng tt · về "Chờ quyết toán" (quyết toán làm bên này)', array( 'VENDING', 'vending', 'tt', 'Chờ quyết toán' ), array( (string) $d1['don_vi'], (string) $d1['khoi'], VHCP_Don::luong_don( $d1 ), (string) $d1['trang_thai'] ) );
teq( '   kỳ = tuần chứa ngày 3/9 (T9/2026 (31/8-6/9/2026))', 'T9/2026 (31/8-6/9/2026)', VHCP_Util::fmt( $d1['ky'] ) );
teq( '   người lập = người đề xuất bên Vending, người DUYỆT = người duyệt bên Vending, người QT còn trống (kế toán bên này)', array( 'Nguyễn Văn Kỹ', 'Trần Quản Lý', '' ), array( (string) $d1['nguoi_lap'], (string) $d1['nguoi_duyet'], (string) $d1['nguoi_qt'] ) );
t( '   ghi chú mang mã Vending + số HĐ + note', false !== strpos( (string) $d1['ghi_chu'], '[Vending CP-2026-0001]' ) && false !== strpos( (string) $d1['ghi_chu'], 'HD001' ), $d1['ghi_chu'] );
teq( '   hoá đơn tổng = link ảnh bên Vending', 'https://vending.kh.vn/wp-content/uploads/hd001.jpg', (string) $d1['hoa_don_qt'] );
$ln = $g1['lines'][0];
teq( '🔴 dòng: cơ sở = Bộ phận POSH · loại = Xăng xe · thành tiền = thực mua = 350.000 · ngày 03/09', array( 'POSH', 'Xăng xe', 350000.0, 350000.0 ), array( $ln['coso'], $ln['nhom'], (float) $ln['thanhTien'], (float) $ln['thucMua'] ) );
t( '   ngày dòng 03/09/2026', false !== strpos( (string) $ln['ngay'], '03/09/2026' ) || '2026-09-03' === (string) $ln['ngay'], $ln['ngay'] );
teq( '🔴 thực chi tính ngay ("Chờ quyết toán" đứng sau mốc cấp tiền — tiền đã chi bên Vending)', 350000.0, (float) VHCP_Don::thuc_chi( $ln['thanhTien'], $ln['thucMua'], $d1['trang_thai'] ) );
$d2 = VHCP_Don::don_row( $map['1002'] );
teq( '   đơn 1002 (Vending "Đã quyết toán") cũng về Chờ quyết toán, cơ sở JP', array( 'Chờ quyết toán', 'JP' ), array( (string) $d2['trang_thai'], VHCP_Don::get_don( $map['1002'], false )['lines'][0]['coso'] ) );
teq( '   đơn 1004 (Vending "Đã duyệt") sang được, cơ sở Thị trường', 'Thị trường', VHCP_Don::get_don( $map['1004'], false )['lines'][0]['coso'] );

/* danh mục đã gieo */
VHCP_Cfg::clear_cache(); $cfg = VHCP_Cfg::get_config();
$cs_vd = array_values( array_filter( $cfg['coso'], function ( $x ) { return 'VENDING' === $x['donVi']; } ) );
teq( '🔴 5 cơ sở đơn vị VENDING, mảng "Chi Phí Vending"', array( 5, 'Chi Phí Vending' ), array( count( $cs_vd ), $cs_vd[0]['phanLoaiLon'] ) );
teq( '   tên = 5 bộ phận', VHCP_Vending::BO_PHAN, array_column( $cs_vd, 'ten' ) );
$loai_vd = array_values( array_filter( $cfg['loaiChiPhi'], function ( $x ) { return 'vending' === $x['khoi']; } ) );
teq( '🔴 10 loại khối vending, TK Nợ trống, đầu mục Chi Phí Vending', array( 10, '', 'Chi Phí Vending' ), array( count( $loai_vd ), $loai_vd[0]['tkNo'], $loai_vd[0]['dauMuc'] ) );
teq( '   gieo lại không nhân đôi', array( 'coso' => 0, 'loai' => 0 ), VHCP_Vending::gieo_danh_muc() );

/* ── 4. Đẩy/kéo lại: cập nhật đơn còn Chờ quyết toán, không nhập đôi; đơn kế toán ĐÃ CHỐT giữ nguyên ── */
$KHO[0]['amount'] = 400000; $KHO[0]['content'] = 'Đổ xăng (sửa)'; $KHO[2]['status'] = 'Đã duyệt';   // 1001 sửa tiền · 1003 nay đã duyệt
$GLOBALS['VHCP_HTTP'] = array( 'vending.kh.vn/wp-json/vending-hcmc/v1/chi-phi' => array( 'code' => 200, 'body' => $json( $KHO ) ) );
global $wpdb; $t_don = VHCP_DB::t( 'don' );
/* Kế toán bên này đã quyết toán đơn 1002 */
$wpdb->query( $wpdb->prepare( "UPDATE $t_don SET trang_thai=%s, nguoi_qt=%s WHERE ma_don=%s", 'Đã quyết toán', 'Kế Toán A', $map['1002'] ) );
$r = VHCP_Vending::dong_bo( array( 'thang' => '2026-09' ) );
teq( '🔴 lần 2: mới 1 (1003 vừa duyệt) · cập nhật 2 (1001, 1004 còn Chờ QT) · đã chốt giữ 1 (1002) · bỏ qua 1 (Từ chối)', array( 1, 2, 1, 1 ), array( $r['moi'], $r['capNhat'], $r['daChot'], $r['boQua'] ) );
teq( '   không gieo thêm', array( 'coso' => 0, 'loai' => 0 ), $r['gieo'] );
$g1 = VHCP_Don::get_don( $m1, false );
teq( '🔴 1001 cập nhật tiền 400.000 và nội dung, vẫn cùng mã đơn, vẫn Chờ quyết toán', array( 400000.0, 'Đổ xăng (sửa)', 1, 'Chờ quyết toán' ), array( (float) $g1['lines'][0]['thucMua'], $g1['lines'][0]['noiDung'], count( $g1['lines'] ), $g1['don']['trangThai'] ) );
teq( '🔴 1002 kế toán đã chốt → không bị kéo lùi, người QT giữ', array( 'Đã quyết toán', 'Kế Toán A' ), array( (string) VHCP_Don::don_row( $map['1002'] )['trang_thai'], (string) VHCP_Don::don_row( $map['1002'] )['nguoi_qt'] ) );
$map = VHCP_Meta::get_json( 'vending_map', array() );
teq( '   bản đồ giờ 4 khoá', 4, count( $map ) );
teq( '   tổng đơn VENDING trong sổ = 4 (không nhân đôi)', 4, (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t_don WHERE don_vi=%s", 'VENDING' ) ) );

/* ── 5. Ra tệp MISA: luồng tt sẵn xuất là SAU "Đã thanh toán" — kế toán quyết toán + thanh toán xong mới ra ── */
$x = VHCP_Misa::export_misa( 'all', 'chuaxuat', 'all', 'soct', 'all', 'Chi Phí Vending' );
teq( '🔴 chưa ai thanh toán → mảng Chi Phí Vending chưa có dòng "chưa xuất" (đúng: quyết toán làm bên này trước)', 0, $x['count'] );
$wpdb->query( $wpdb->prepare( "UPDATE $t_don SET trang_thai=%s WHERE ma_don=%s", 'Đã thanh toán', $map['1002'] ) );
$x = VHCP_Misa::export_misa( 'all', 'chuaxuat', 'all', 'soct', 'all', 'Chi Phí Vending' );
t( '🔴 kế toán thanh toán 1002 xong → 1 dòng ra tệp, mảng "Chi Phí Vending" có trong ô lọc', 1 === $x['count'] && in_array( 'Chi Phí Vending', $x['mangDs'], true ), array( $x['count'], $x['mangDs'] ) );
t( '   cảnh báo thiếu TK Nợ cho loại Bảo trì (kế toán chưa gán) — đúng ý "để trống TK Nợ"', (bool) count( array_filter( $x['warn'], function ( $w ) { return false !== mb_strpos( $w, 'Bảo trì' ); } ) ), $x['warn'] );

/* ── 5b. ĐIỂM NHẬN ĐẨY (REST) — chiều chính ─────────────────────────────────────────────────── */
$rq = function ( $body, $khoa = null ) { $r = new WP_REST_Request( 'POST', null === $khoa ? array() : array( 'X-KHH-Khoa' => $khoa ) ); $r->set_body( json_encode( $body, JSON_UNESCAPED_UNICODE ) ); return $r; };
$e = VHCP_Vending::duoc_nhan( $rq( array(), 'sai' ) );
t( '🔴 điểm nhận: khoá sai → 401', is_wp_error( $e ) && 401 === $e->get_error_data()['status'], $e );
$e = VHCP_Vending::duoc_nhan( $rq( array() ) );
t( '   không header → 401', is_wp_error( $e ) && 401 === $e->get_error_data()['status'] );
t( '   đúng khoá → mở', true === VHCP_Vending::duoc_nhan( $rq( array(), 'KHOA-VENDING-1234567890' ) ) );
$rn = VHCP_Vending::rest_nhan( $rq( array( 'web' => 'VENDING HCMC', 'khoan' => array(
	array( 'id' => 2001, 'code' => 'CP-2026-0101', 'date' => '2026-09-26', 'department' => 'POSH', 'type' => 'Vật tư', 'content' => 'Đẩy tự động', 'amount' => 75000, 'requester' => 'Nguyễn Văn Kỹ', 'approver' => 'Trần Quản Lý', 'status' => 'Đã duyệt' ),
	array( 'id' => 2002, 'code' => 'CP-2026-0102', 'date' => '2026-09-26', 'department' => 'JP', 'type' => 'Vật tư', 'content' => 'chờ', 'amount' => 1, 'status' => 'Chờ duyệt' ),
) ), 'KHOA-VENDING-1234567890' ) );
t( '🔴 rest_nhan: 1 mới (Đã duyệt) · 1 bỏ qua (Chờ duyệt), nguồn ghi "đẩy từ VENDING HCMC"', ! empty( $rn['success'] ) && 1 === $rn['moi'] && 1 === $rn['boQua'] && 'đẩy từ VENDING HCMC' === $rn['nguon'], $rn );
$map = VHCP_Meta::get_json( 'vending_map', array() );
t( '   đơn đẩy sang ở Chờ quyết toán, cơ sở POSH', isset( $map['2001'] ) && 'Chờ quyết toán' === (string) VHCP_Don::don_row( $map['2001'] )['trang_thai'], $map );
$e = VHCP_Vending::rest_nhan( $rq( array( 'web' => 'x', 'khoan' => array() ), 'KHOA-VENDING-1234567890' ) );
t( '   gói rỗng → 400', is_wp_error( $e ) && 400 === $e->get_error_data()['status'] );

/* ── 5c. Địa chỉ cũ còn dính ?query, hoặc trang con trả HTML 200 → tự lùi về gốc web ─────────── */
update_option( 'vhcp_vd_url', 'https://vending.kh.vn/app', false );
$GLOBALS['VHCP_HTTP'] = array(
	'https://vending.kh.vn/app/wp-json/vending-hcmc/v1/chi-phi' => array( 'code' => 200, 'body' => '<!DOCTYPE html><html><body>VENDING app</body></html>' ),
	'https://vending.kh.vn/wp-json/vending-hcmc/v1/chi-phi'     => array( 'code' => 200, 'body' => $json( array() ) ),
);
$GLOBALS['VHCP_DA_GET'] = array();
$kq = VHCP_Vending::goi( '2026-09-01', '2026-09-30' );
t( '🔴 trang con trả HTML 200 → thử lại ở gốc web và được (hai lượt GET)', ! empty( $kq['ok'] ) && 2 === count( $GLOBALS['VHCP_DA_GET'] ), array( $kq, $GLOBALS['VHCP_DA_GET'] ) );
$GLOBALS['VHCP_HTTP'] = array( 'vending.kh.vn' => array( 'code' => 200, 'body' => '<!DOCTYPE html><html>app</html>' ) );
$kq = VHCP_Vending::goi( '2026-09-01', '2026-09-30' );
t( '   gốc cũng trả HTML → câu báo nói rõ "TRANG HTML", nhắc điền gốc web / cache chặn wp-json', empty( $kq['ok'] ) && false !== mb_strpos( $kq['error'], 'TRANG HTML' ) && false !== mb_strpos( $kq['error'], 'wp-json' ), $kq );
update_option( 'vhcp_vd_url', 'https://vending.kh.vn', false );

/* ── 5d. Hosting chặn /wp-json/ (403 kèm trang HTML) → tự sang cửa dự phòng admin-ajax.php của Vending ── */
$GLOBALS['VHCP_HTTP'] = array(
	'/wp-json/vending-hcmc/v1/chi-phi' => array( 'code' => 403, 'body' => '<!DOCTYPE html><html>403 Forbidden</html>' ),
	'/wp-admin/admin-ajax.php?action=vhcm_chi_phi' => array( 'code' => 200, 'body' => $json( array( $KHO[0] ) ) ),
);
$GLOBALS['VHCP_DA_GET'] = array();
$kq = VHCP_Vending::goi( '2026-09-01', '2026-09-30' );
t( '🔴 /wp-json/ bị chặn 403 HTML → sang admin-ajax.php và được, đánh dấu đường', ! empty( $kq['ok'] ) && 'admin-ajax' === $kq['duong'] && 1 === count( $kq['chiPhi'] ), $kq );
t( '   địa chỉ dự phòng đúng: …/wp-admin/admin-ajax.php?action=vhcm_chi_phi&tu=…', (bool) preg_grep( '#/wp-admin/admin-ajax\.php\?action=vhcm_chi_phi&tu=2026-09-01#', $GLOBALS['VHCP_DA_GET'] ), $GLOBALS['VHCP_DA_GET'] );
$GLOBALS['VHCP_HTTP'] = array( 'vending.kh.vn' => array( 'code' => 403, 'body' => '<html>403</html>' ) );
$kq = VHCP_Vending::goi( '2026-09-01', '2026-09-30' );
t( '   cả hai cửa đều chặn → câu báo nhắc đã thử cửa dự phòng', empty( $kq['ok'] ) && false !== mb_strpos( $kq['error'], 'admin-ajax' ), $kq );

/* ── 5e. Ping từ Vending + cửa dự phòng nhận ajax_nhan ─────────────────────────────────────── */
$pg = VHCP_Vending::rest_nhan( $rq( array( 'ping' => 1 ), 'KHOA-VENDING-1234567890' ) );
$so_truoc = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t_don WHERE don_vi=%s", 'VENDING' ) );
t( '   ping → success, kèm tên web + bản Chi phí, không lập đơn', ! empty( $pg['success'] ) && ! empty( $pg['ping'] ) && isset( $pg['banChiPhi'] ) && $so_truoc === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t_don WHERE don_vi=%s", 'VENDING' ) ), $pg );
if ( ! function_exists( 'wp_send_json' ) ) { function wp_send_json( $d, $ma = null ) { throw new RuntimeException( 'JSON:' . ( null === $ma ? 200 : $ma ) . ':' . json_encode( $d, JSON_UNESCAPED_UNICODE ) ); } }
$bat = function ( $khoa ) { $_POST['khoa'] = $khoa; try { VHCP_Vending::ajax_nhan(); } catch ( RuntimeException $e ) { return $e->getMessage(); } return ''; };
t( '🔴 ajax_nhan: khoá sai → JSON 401', 0 === strpos( $bat( 'sai' ), 'JSON:401:' ), $bat( 'sai' ) );
t( '   ajax_nhan: khoá đúng, thân rỗng (CLI) → JSON 400 "gói rỗng" — chứng tỏ đã qua cửa khoá vào bộ xử lý', 0 === strpos( $bat( 'KHOA-VENDING-1234567890' ), 'JSON:400:' ), $bat( 'KHOA-VENDING-1234567890' ) );
unset( $_POST['khoa'] );
$ch = VHCP_Vending::cau_hinh();
t( '   cau_hinh bày cả địa chỉ dự phòng admin-ajax', false !== strpos( $ch['diaChiNhanAjax'], 'admin-ajax.php?action=vhcp_vending_nhan' ), $ch );

/* ── 6. Chối khi chưa khai / khoá sai ─────────────────────────────────────────────────────── */
$GLOBALS['VHCP_HTTP'] = array( 'vending.kh.vn' => array( 'code' => 401, 'body' => '{"code":"vhcm_khoa","message":"x"}' ) );
$r = VHCP_Vending::dong_bo( array( 'thang' => '2026-09' ) );
t( '   web chối khoá → câu bảo kiểm khoá ở cả hai web, không đụng sổ', empty( $r['success'] ) && false !== mb_strpos( $r['error'], 'khoá' ), $r );
$GLOBALS['VHCP_HTTP'] = array( 'vending.kh.vn' => array( 'code' => 404, 'body' => '' ) );
$r = VHCP_Vending::dong_bo( array( 'thang' => '2026-09' ) );
t( '   404 → nhắc cài plugin Vending 1.81.0', empty( $r['success'] ) && false !== mb_strpos( $r['error'], '1.81.0' ), $r );

if ( $TRUOT ) { echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n"; foreach ( $TRUOT as $x ) { echo "  · $x\n"; } exit( 1 ); }
echo "\n✓ SẠCH — $DAT phép: web Vending đẩy sang thành đơn Chờ quyết toán (khối Vending), kéo dự phòng cùng luật, không nhập đôi, kế toán chốt thì giữ.\n";
