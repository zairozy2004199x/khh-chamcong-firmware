<?php
/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * KÉO CHI PHÍ VENDING HCMC VỀ THÀNH ĐƠN THẬT — `VHCP_Vending`.
 * Anh Thắng 25/09/2026: "nối chi phí từ web khác qua chi phí của web anh" → nhập thành đơn thật ·
 * khối "Vending", mỗi Bộ phận = một cơ sở · trạng thái theo bên Vending.
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
t( '🔴 gói màn không có khoá; san=false khi chưa khai', ! array_key_exists( 'khoa', $ch ) && false === $ch['san'] && 0 === $ch['soDaNhap'], $ch );
VHCP_Auth::dat_vai_tro( 'Kế toán cá nhân', 'Kế Toán A' );
$r = VHCP_Cfg::save_config( array( 'vending' => array( 'url' => 'https://vending.kh.vn', 'khoa' => 'KHOA-VENDING-1234567890' ) ) );
t( '🔴 Kế toán không đổi được kết nối', empty( $r['success'] ), $r );
VHCP_Auth::dat_vai_tro( 'Admin', 'Anh Thắng' );
$r = VHCP_Cfg::save_config( array( 'vending' => array( 'url' => 'https://vending.kh.vn/?vending_hcmc=1', 'khoa' => 'KHOA-VENDING-1234567890' ) ) );
t( '   Admin lưu được', ! empty( $r['success'] ), $r );
VHCP_Cfg::save_config( array( 'vending' => array( 'url' => 'https://vending.kh.vn', 'khoa' => '' ) ) );
teq( '🔴 khoá rỗng = GIỮ', 'KHOA-VENDING-1234567890', get_option( 'vhcp_vd_khoa' ) );
teq( '   san=true', true, VHCP_Vending::cau_hinh()['san'] );

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
teq( '🔴 mới 2 (Đã thanh toán + Đã quyết toán) · bỏ qua 3 (Chờ duyệt, Đã duyệt, Từ chối) · bộ phận lạ 1', array( 2, 0, 3, 1, 0 ), array( $r['moi'], $r['capNhat'], $r['boQua'], $r['boQuaBoPhan'], $r['daXuat'] ) );
teq( '🔴 gieo 5 cơ sở + 10 loại lần đầu', array( 'coso' => 5, 'loai' => 10 ), $r['gieo'] );
t( '   lỗi chỉ ghi khoản bộ phận lạ, nêu mã', 1 === count( $r['loi'] ) && false !== mb_strpos( $r['loi'][0], 'CP-2026-0005' ) && false !== mb_strpos( $r['loi'][0], 'Kho lạ' ), $r['loi'] );
$map = VHCP_Meta::get_json( 'vending_map', array() );
teq( '   bản đồ id → mã đơn có 2 khoá', array( '1001', '1002' ), array_map( 'strval', array_keys( $map ) ) );
$m1 = $map['1001']; $d1 = VHCP_Don::don_row( $m1 ); $g1 = VHCP_Don::get_don( $m1, false );
teq( '🔴 đơn 1001: đơn vị VENDING · khối vending · luồng tt · trạng thái Đã thanh toán', array( 'VENDING', 'vending', 'tt', 'Đã thanh toán' ), array( (string) $d1['don_vi'], (string) $d1['khoi'], VHCP_Don::luong_don( $d1 ), (string) $d1['trang_thai'] ) );
teq( '   kỳ = tuần chứa ngày 3/9 (T9/2026 (31/8-6/9/2026))', 'T9/2026 (31/8-6/9/2026)', VHCP_Util::fmt( $d1['ky'] ) );
teq( '   người lập = người đề xuất bên Vending, người QT = người duyệt', array( 'Nguyễn Văn Kỹ', 'Trần Quản Lý' ), array( (string) $d1['nguoi_lap'], (string) $d1['nguoi_qt'] ) );
t( '   ghi chú mang mã Vending + số HĐ + note', false !== strpos( (string) $d1['ghi_chu'], '[Vending CP-2026-0001]' ) && false !== strpos( (string) $d1['ghi_chu'], 'HD001' ), $d1['ghi_chu'] );
teq( '   hoá đơn tổng = link ảnh bên Vending', 'https://vending.kh.vn/wp-content/uploads/hd001.jpg', (string) $d1['hoa_don_qt'] );
$ln = $g1['lines'][0];
teq( '🔴 dòng: cơ sở = Bộ phận POSH · loại = Xăng xe · thành tiền = thực mua = 350.000 · ngày 03/09', array( 'POSH', 'Xăng xe', 350000.0, 350000.0 ), array( $ln['coso'], $ln['nhom'], (float) $ln['thanhTien'], (float) $ln['thucMua'] ) );
t( '   ngày dòng 03/09/2026', false !== strpos( (string) $ln['ngay'], '03/09/2026' ) || '2026-09-03' === (string) $ln['ngay'], $ln['ngay'] );
teq( '🔴 thực chi tính ngay (đã cấp tiền theo luồng)', 350000.0, (float) VHCP_Don::thuc_chi( $ln['thanhTien'], $ln['thucMua'], $d1['trang_thai'] ) );
$d2 = VHCP_Don::don_row( $map['1002'] );
teq( '   đơn 1002: Đã quyết toán, cơ sở JP', array( 'Đã quyết toán', 'JP' ), array( (string) $d2['trang_thai'], VHCP_Don::get_don( $map['1002'], false )['lines'][0]['coso'] ) );

/* danh mục đã gieo */
VHCP_Cfg::clear_cache(); $cfg = VHCP_Cfg::get_config();
$cs_vd = array_values( array_filter( $cfg['coso'], function ( $x ) { return 'VENDING' === $x['donVi']; } ) );
teq( '🔴 5 cơ sở đơn vị VENDING, mảng "Chi Phí Vending"', array( 5, 'Chi Phí Vending' ), array( count( $cs_vd ), $cs_vd[0]['phanLoaiLon'] ) );
teq( '   tên = 5 bộ phận', VHCP_Vending::BO_PHAN, array_column( $cs_vd, 'ten' ) );
$loai_vd = array_values( array_filter( $cfg['loaiChiPhi'], function ( $x ) { return 'vending' === $x['khoi']; } ) );
teq( '🔴 10 loại khối vending, TK Nợ trống, đầu mục Chi Phí Vending', array( 10, '', 'Chi Phí Vending' ), array( count( $loai_vd ), $loai_vd[0]['tkNo'], $loai_vd[0]['dauMuc'] ) );
teq( '   gieo lại không nhân đôi', array( 'coso' => 0, 'loai' => 0 ), VHCP_Vending::gieo_danh_muc() );

/* ── 4. Kéo lại: cập nhật, không nhập đôi; đơn đã xuất MISA giữ nguyên ─────────────────────── */
$KHO[0]['amount'] = 400000; $KHO[0]['content'] = 'Đổ xăng (sửa)'; $KHO[3]['status'] = 'Đã thanh toán';   // 1001 sửa tiền · 1004 nay đã trả
$GLOBALS['VHCP_HTTP'] = array( 'vending.kh.vn/wp-json/vending-hcmc/v1/chi-phi' => array( 'code' => 200, 'body' => $json( $KHO ) ) );
global $wpdb; $t_don = VHCP_DB::t( 'don' );
$wpdb->query( $wpdb->prepare( "UPDATE $t_don SET trang_thai=%s WHERE ma_don=%s", 'Đã xuất MISA', $map['1002'] ) );
$r = VHCP_Vending::dong_bo( array( 'thang' => '2026-09' ) );
teq( '🔴 lần 2: mới 1 (1004 vừa trả) · cập nhật 1 (1001) · đã xuất MISA giữ 1 (1002) · bỏ qua 2', array( 1, 1, 1, 2 ), array( $r['moi'], $r['capNhat'], $r['daXuat'], $r['boQua'] ) );
teq( '   không gieo thêm', array( 'coso' => 0, 'loai' => 0 ), $r['gieo'] );
$g1 = VHCP_Don::get_don( $m1, false );
teq( '🔴 1001 cập nhật tiền 400.000 và nội dung, vẫn cùng mã đơn', array( 400000.0, 'Đổ xăng (sửa)', 1 ), array( (float) $g1['lines'][0]['thucMua'], $g1['lines'][0]['noiDung'], count( $g1['lines'] ) ) );
teq( '   1002 đã xuất MISA → trạng thái không bị kéo lùi', 'Đã xuất MISA', (string) VHCP_Don::don_row( $map['1002'] )['trang_thai'] );
$map = VHCP_Meta::get_json( 'vending_map', array() );
teq( '   bản đồ giờ 3 khoá', 3, count( $map ) );
teq( '   tổng đơn VENDING trong sổ = 3 (không nhân đôi)', 3, (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t_don WHERE don_vi=%s", 'VENDING' ) ) );

/* ── 5. Ra tệp MISA: đơn tt "Đã thanh toán" là sẵn xuất; mảng Chi Phí Vending lọc riêng được ── */
$x = VHCP_Misa::export_misa( 'all', 'chuaxuat', 'all', 'soct', 'all', 'Chi Phí Vending' );
t( '🔴 lọc mảng "Chi Phí Vending": ra 2 dòng (1001 · 1004), 1002 đã xuất không vào "chưa xuất"', 2 === $x['count'] && in_array( 'Chi Phí Vending', $x['mangDs'], true ), array( $x['count'], $x['mangDs'] ) );
t( '   cảnh báo thiếu TK Nợ cho loại Xăng xe (kế toán chưa gán) — đúng ý "để trống TK Nợ"', (bool) count( array_filter( $x['warn'], function ( $w ) { return false !== mb_strpos( $w, 'Xăng xe' ); } ) ), $x['warn'] );

/* ── 6. Chối khi chưa khai / khoá sai ─────────────────────────────────────────────────────── */
$GLOBALS['VHCP_HTTP'] = array( 'vending.kh.vn' => array( 'code' => 401, 'body' => '{"code":"vhcm_khoa","message":"x"}' ) );
$r = VHCP_Vending::dong_bo( array( 'thang' => '2026-09' ) );
t( '   web chối khoá → câu bảo kiểm khoá ở cả hai web, không đụng sổ', empty( $r['success'] ) && false !== mb_strpos( $r['error'], 'khoá' ), $r );
$GLOBALS['VHCP_HTTP'] = array( 'vending.kh.vn' => array( 'code' => 404, 'body' => '' ) );
$r = VHCP_Vending::dong_bo( array( 'thang' => '2026-09' ) );
t( '   404 → nhắc cài plugin Vending 1.81.0', empty( $r['success'] ) && false !== mb_strpos( $r['error'], '1.81.0' ), $r );

if ( $TRUOT ) { echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n"; foreach ( $TRUOT as $x ) { echo "  · $x\n"; } exit( 1 ); }
echo "\n✓ SẠCH — $DAT phép: kéo chi phí Vending về thành đơn thật, khối Vending, không nhập đôi, ra MISA riêng mảng.\n";
