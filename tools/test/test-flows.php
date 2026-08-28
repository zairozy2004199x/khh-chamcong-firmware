<?php
/**
 * Kiểm nghiệm logic plugin Vận Hành Chi Phí (chạy bằng PHP CLI, không cần WordPress).
 *   php tools/test/test-flows.php
 */

require_once __DIR__ . '/wp-stub.php';
vhcp_test_boot( dirname( dirname( __DIR__ ) ) . '/wordpress/vhcp-chi-phi' );

$GLOBALS['T_OK'] = 0;
$GLOBALS['T_NG'] = array();

/* 🔴 CHẾT GIỮA ĐƯỜNG PHẢI NÓI RA. Không có chỗ này thì một lỗi PHP nghiêm trọng (câu SQL hỏng,
   lớp chưa nạp) làm bài kiểm dừng ngang, KHÔNG in dòng tổng kết nào — và người chạy nhìn màn
   hình trống rồi tưởng chưa chạy. Tệ hơn: đếm số dòng "✗" ra 0, trông y như SẠCH.
   Đúng chuyện vừa gặp khi phá thử `d.coso` (26/08/2026). Cùng lối với `kiem-noi-bo.php`. */
register_shutdown_function( function () {
	$e = error_get_last();
	if ( ! $e || ! in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) { return; }
	echo "\n🔴 BÀI KIỂM CHẾT GIỮA ĐƯỜNG: " . $e['message'] . "\n   tại " . $e['file'] . ':' . $e['line'] . "\n";
	echo '  ✗ chết giữa đường sau ' . $GLOBALS['T_OK'] . " phép đã đạt\n";
} );

function t( $name, $cond, $got = null ) {
	if ( $cond ) { $GLOBALS['T_OK']++; return; }
	$GLOBALS['T_NG'][] = $name . ( $got === null ? '' : ' → nhận được: ' . var_export( $got, true ) );
}

function teq( $name, $expect, $actual ) {
	t( $name . ' (mong ' . var_export( $expect, true ) . ')', $expect == $actual, $actual );
}

// ---------------------------------------------------------------- 1. cấu hình mặc định
$cfg = VHCP_Cfg::cfg_static();
teq( 'seed: 14 cơ sở', 14, count( $cfg['coso'] ) );
teq( 'seed: 13 nhóm (11 + 2 kỹ thuật)', 13, count( $cfg['nhom'] ) );
teq( 'seed: 2 phân loại TT', 2, count( $cfg['phanloai'] ) );
$nhom_kt = array();
foreach ( $cfg['nhom'] as $n ) { if ( $n['boPhan'] === 'Kỹ thuật' ) { $nhom_kt[] = $n['ten']; } }
teq( 'seed: 2 nhóm thuộc bộ phận Kỹ thuật', 2, count( $nhom_kt ) );

$q = VHCP_Cfg::get_quyen();
t( 'quyền mặc định: Quản lý duyệt tạm ứng', $q['duyetTU']['Quản lý'] === true );
t( 'quyền mặc định: Nhân viên KHÔNG duyệt tạm ứng', $q['duyetTU']['Nhân viên'] === false );

// ---------------------------------------------------------------- 2. đăng nhập
$r = VHCP_Auth::login( '1111' );
t( 'login PIN 1111 = Admin', ! empty( $r['ok'] ) && $r['role'] === 'Admin', $r );
t( 'login phát token 64 ký tự', ! empty( $r['token'] ) && strlen( $r['token'] ) === 64 );
$u = VHCP_Auth::user_by_token( $r['token'] );
teq( 'token tra ra đúng người', 'Admin', $u ? $u['name'] : null );
$bad = VHCP_Auth::login( '9999' );
t( 'login PIN sai bị chặn', empty( $bad['ok'] ) );
t( 'login PIN 3 số bị chặn', empty( VHCP_Auth::login( '111' )['ok'] ) );

// ---------------------------------------------------------------- 3. đơn vận hành
$today = ( new DateTime( 'now', VHCP_Util::tz() ) )->format( 'd/m/Y' );
$d = VHCP_Don::create_don( 'T8/2026 (17/8-23/8/2026)', 'Nguyễn Văn A' );
$ma = $d['maDon'];
t( 'tạo đơn', ! empty( $d['success'] ) && $ma !== '' );

$l1 = VHCP_Don::add_line( $ma, array( 'coso' => 'FARM PHAN THIẾT', 'ngay' => $today, 'phanLoaiTT' => 'Thanh toán cá nhân', 'doiTuong' => 'Nguyễn Văn A', 'nhom' => 'NVL đồ ăn - Mua lẻ', 'noiDung' => 'Thịt heo', 'dvt' => 'kg', 'soLuong' => 10, 'donGia' => 120000, 'thanhTien' => 1200000 ) );
$l2 = VHCP_Don::add_line( $ma, array( 'coso' => 'FARM PHAN THIẾT', 'ngay' => $today, 'phanLoaiTT' => 'Thanh toán cá nhân', 'nhom' => 'Chi phí cơ sở', 'noiDung' => 'Sửa quạt', 'soLuong' => 1, 'donGia' => 300000, 'thanhTien' => 300000 ) );
// MỖI ĐƠN 1 CƠ SỞ: dòng của TÀU TÂN PHÚ phải nằm ở đơn riêng. Để người lập KHÁC để
// không chen vào chuỗi bù trừ luân chuyển của Nguyễn Văn A.
$d_ncc = VHCP_Don::create_don( 'T8/2026 (17/8-23/8/2026)', 'Nguyễn Văn B' );
$ma_ncc = $d_ncc['maDon'];
$l3 = VHCP_Don::add_line( $ma_ncc, array( 'coso' => 'TÀU TÂN PHÚ', 'ngay' => $today, 'phanLoaiTT' => 'Nhà cung cấp', 'doiTuong' => 'CTY ABC', 'nhom' => 'SP Đồ uống - NCC', 'noiDung' => 'Nước ngọt', 'soLuong' => 20, 'donGia' => 15000, 'thanhTien' => 300000, 'thueSuat' => 8 ) );
teq( 'dòng 1..3 đều là hạng mục xin', 0, $l3['phatSinh'] );

$g = VHCP_Don::get_don( $ma );
teq( 'tạm ứng FARM = 1.500.000', 1500000, $g['tamUng']['FARM PHAN THIẾT'] );
t( 'đơn FARM không có cơ sở khác', ! isset( $g['tamUng']['TÀU TÂN PHÚ'] ) );
teq( 'tổng tạm ứng cục = 1.500.000', 1500000, $g['tongCN']['tamUng'] );
teq( 'đã chi cá nhân = 1.500.000', 1500000, $g['tongCN']['thucChi'] );
$g_ncc = VHCP_Don::get_don( $ma_ncc );
teq( 'tạm ứng TÀU TÂN PHÚ = 300.000', 300000, $g_ncc['tamUng']['TÀU TÂN PHÚ'] );
teq( 'đã chi NCC = 300.000', 300000, $g_ncc['tongNCC']['thucChi'] );
teq( 'tiền thuế dòng NCC = 24.000', 24000, $g_ncc['lines'][0]['tienThue'] );
teq( 'ngày dòng hiện dd/MM/yyyy', $today, $g['lines'][0]['ngay'] );
t( 'thực mua để trống', $g['lines'][0]['thucMua'] === '' );

// MỘT ĐƠN = MỘT CƠ SỞ. Đơn trên đã có dòng của FARM PHAN THIẾT và TÀU TÂN PHÚ do dữ liệu
// thử dựng từ trước; kiểm luật khóa trên một đơn mới cho sạch.
$dcs = VHCP_Don::create_don( 'T8/2026 (17/8-23/8/2026)', 'NV Một Cơ Sở' );
$mcs = $dcs['maDon'];
teq( 'đơn mới chưa chốt cơ sở nào', '', VHCP_Don::coso_cua_don( $mcs ) );
$lcs = VHCP_Don::add_line( $mcs, array( 'coso' => 'FARM PHAN THIẾT', 'ngay' => $today, 'phanLoaiTT' => 'Thanh toán cá nhân', 'nhom' => 'Chi phí cơ sở', 'noiDung' => 'Sửa đèn', 'soLuong' => 1, 'donGia' => 100000, 'thanhTien' => 100000 ) );
t( 'dòng đầu vào được', ! empty( $lcs['success'] ) );
teq( 'đơn chốt cơ sở theo dòng đầu', 'FARM PHAN THIẾT', VHCP_Don::coso_cua_don( $mcs ) );
$lcs2 = VHCP_Don::add_line( $mcs, array( 'coso' => 'TÀU TÂN PHÚ', 'ngay' => $today, 'phanLoaiTT' => 'Thanh toán cá nhân', 'nhom' => 'Chi phí cơ sở', 'noiDung' => 'Sửa quạt', 'soLuong' => 1, 'donGia' => 100000, 'thanhTien' => 100000 ) );
t( 'cơ sở khác bị chặn', empty( $lcs2['success'] ) );
t( 'và nói rõ phải tạo đơn mới', strpos( (string) $lcs2['error'], 'tạo đơn mới' ) !== false, $lcs2 );
$lcs3 = VHCP_Don::add_line( $mcs, array( 'coso' => 'FARM PHAN THIẾT', 'ngay' => $today, 'phanLoaiTT' => 'Thanh toán cá nhân', 'nhom' => 'Chi phí cơ sở', 'noiDung' => 'Sửa quạt', 'soLuong' => 1, 'donGia' => 100000, 'thanhTien' => 100000 ) );
t( 'cùng cơ sở thì thêm tiếp được', ! empty( $lcs3['success'] ) );
t( 'sửa dòng sang cơ sở khác cũng bị chặn', empty( VHCP_Don::update_line( $lcs3['id'], array( 'coso' => 'TÀU TÂN PHÚ', 'nhom' => 'Chi phí cơ sở', 'noiDung' => 'Sửa quạt', 'soLuong' => 1, 'donGia' => 100000, 'thanhTien' => 100000 ) )['success'] ) );
teq( 'get_don trả cơ sở đã chốt', 'FARM PHAN THIẾT', VHCP_Don::get_don( $mcs )['don']['cosoDon'] );
VHCP_Don::delete_don_admin( $mcs );

// dự phòng khi còn Nháp. BÙ TRỪ do hệ thống tính từ kỳ trước: số gửi lên bị BỎ QUA,
// nếu không thì nhân viên gõ số dương là xin nhiều hơn phần đáng được ứng.
t( 'set dự phòng khi Nháp', ! empty( VHCP_Don::set_tu_extra( $ma, 200000, -100000 )['success'] ) );
$g = VHCP_Don::get_don( $ma );
teq( 'bù trừ gửi từ giao diện bị bỏ qua', 0, VHCP_Util::num( $g['don']['buTru'] ) );
t( 'nói rõ vì sao bằng 0', strpos( (string) $g['don']['buTruAuto']['lyDo'], 'chưa có kỳ nào trước' ) !== false, $g['don']['buTruAuto'] );
teq( 'tạm ứng cục = hạng mục + dự phòng', 1700000, $g['tongCN']['tamUng'] );

// quy trình
t( 'gửi duyệt tạm ứng', ! empty( VHCP_Don::gui_duyet_tam_ung( $ma )['success'] ) );
t( 'không cấp tiền trước khi duyệt', empty( VHCP_Don::cap_tam_ung( $ma, 'KT', 'Tiền mặt' )['success'] ) );
t( 'quản lý duyệt tạm ứng', ! empty( VHCP_Don::duyet_tam_ung( $ma, 'Trần Quản Lý', '' )['success'] ) );
teq( 'trạng thái sau duyệt', 'Chờ cấp tạm ứng', VHCP_Don::don_row( $ma )['trang_thai'] );
t( 'chuyển khoản thiếu ảnh bị chặn', empty( VHCP_Don::cap_tam_ung( $ma, 'KT', 'Chuyển khoản', '' )['success'] ) );
t( 'kế toán cấp tạm ứng tiền mặt', ! empty( VHCP_Don::cap_tam_ung( $ma, 'Lê Kế Toán', 'Tiền mặt' )['success'] ) );
teq( 'trạng thái sau cấp', 'Đã cấp tạm ứng', VHCP_Don::don_row( $ma )['trang_thai'] );

// sau khi cấp: thêm dòng = phát sinh, sửa/xóa hạng mục xin bị khóa đúng luật
$l4 = VHCP_Don::add_line( $ma, array( 'coso' => 'FARM PHAN THIẾT', 'ngay' => $today, 'phanLoaiTT' => 'Thanh toán cá nhân', 'nhom' => 'Phát sinh', 'noiDung' => 'Mua thêm đá', 'soLuong' => 1, 'donGia' => 50000, 'thanhTien' => 50000 ) );
teq( 'dòng thêm sau khi cấp = phát sinh', 1, $l4['phatSinh'] );
/* Canh chính phép thử: nếu vai trò lúc này là Admin thì phép "không xóa được" ở dưới sẽ
   xanh vì lý do sai (Admin xóa được ở mọi trạng thái). Chốt vai trò thường trước đã. */
$_vt_truoc = VHCP_Auth::vai_tro();
VHCP_Auth::dat_vai_tro( 'Nhân viên' );
t( 'không xóa được hạng mục xin sau khi cấp', empty( VHCP_Don::delete_line( $l1['id'] )['success'] ) );
t( 'xóa được dòng phát sinh', ! empty( VHCP_Don::delete_line( $l4['id'] )['success'] ) );

/* ADMIN XÓA ĐƯỢC DÒNG DÙ ĐƠN ĐÃ KHÓA — và phải để lại vết.
   Dòng nhập lỗi lọt tới lúc đơn chốt thì Admin là đường sửa duy nhất còn lại; khóa cứng chỉ
   đẩy người ta vào sửa thẳng cơ sở dữ liệu, chỗ đó không ai kiểm được. */
$l_adm = VHCP_Don::add_line( $ma, array( 'coso' => 'FARM PHAN THIẾT', 'ngay' => $today, 'phanLoaiTT' => 'Thanh toán cá nhân', 'nhom' => 'Nguyên vật liệu', 'noiDung' => 'DÒNG NHẬP LỖI', 'soLuong' => 1, 'donGia' => 777000, 'thanhTien' => 777000 ) );
$wpdb->update( VHCP_DB::t( 'chiphi' ), array( 'phat_sinh' => 0 ), array( 'id' => $l_adm['id'] ) );   // ép thành hạng mục xin để chạm đúng nhánh bị khóa
t( 'nhân viên KHÔNG xóa được dòng đó', empty( VHCP_Don::delete_line( $l_adm['id'] )['success'] ) );

VHCP_Auth::dat_vai_tro( 'Admin' );
$_log_truoc = count( VHCP_Log::get_log( array( 'limit' => 200 ) )['items'] );
t( 'Admin XÓA ĐƯỢC dòng dù đơn đã cấp', ! empty( VHCP_Don::delete_line( $l_adm['id'] )['success'] ) );
$_items = VHCP_Log::get_log( array( 'limit' => 200 ) )['items'];
$_co_vet = false;
foreach ( $_items as $_it ) {
	// get_log trả về khóa hanhDong/chiTiet, KHÔNG phải action/detail (đó là khóa lúc GHI).
	if ( strpos( (string) $_it['hanhDong'], 'Admin xóa dòng' ) !== false
		&& strpos( (string) $_it['chiTiet'], 'DÒNG NHẬP LỖI' ) !== false
		&& preg_match( '/777[.,]?000/', (string) $_it['chiTiet'] ) ) { $_co_vet = true; break; }
}
t( 'xóa bằng quyền Admin có ghi nhật ký kèm nội dung và số tiền', $_co_vet );
t( 'dòng đã biến mất thật', null === VHCP_Don::line_row( $l_adm['id'] ) );

/* 🔴 ĐỔI Ý CÓ CHỦ Ý (anh Thắng 26/08: *"Phía bên phải là lịch sử chỉnh đơn. để biết ai chỉnh gì
   trong này."*).
   Trước đây xoá ở trạng thái BÌNH THƯỜNG thì KHÔNG ghi vết, lý do là "ghi cả những lần thường
   là làm loãng, tra lại không thấy gì". Lý do ấy đúng khi nhật ký chỉ có MỘT cuốn chung cho cả
   hệ. Nay mỗi đơn có sổ riêng (`nhat_ky_don` lọc theo mã đơn), nên ghi đủ KHÔNG làm loãng gì —
   và đúng lúc quyền sửa vừa mở rộng ra mọi trạng thái chưa chốt, không ghi đủ mới là chỗ hở:
   một con số đổi sau khi quản lý đã duyệt mà không ai dựng lại được là ai đổi. */
$l_th = VHCP_Don::add_line( $ma, array( 'coso' => 'FARM PHAN THIẾT', 'ngay' => $today, 'phanLoaiTT' => 'Thanh toán cá nhân', 'nhom' => 'Phát sinh', 'noiDung' => 'PHÁT SINH THƯỜNG', 'thanhTien' => 11000 ) );
VHCP_Don::delete_line( $l_th['id'] );
$_nk = VHCP_Don::nhat_ky_don( $ma, 100 )['items'];
$_vet_th = null;
foreach ( $_nk as $_it ) {
	if ( strpos( (string) $_it['chiTiet'], 'PHÁT SINH THƯỜNG' ) !== false
		&& strpos( (string) $_it['hanhDong'], 'Xoá' ) !== false ) { $_vet_th = $_it; break; }
}
t( 'xoá dòng ở trạng thái bình thường VẪN ghi vết (ai xoá gì)', null !== $_vet_th, $_nk );
/* ⚠️ Nhưng KHÔNG mang nhãn phá khoá — hai chuyện khác nhau: xoá đúng luật, và Admin phá khoá
   xoá ở trạng thái người khác không xoá được. Gộp nhãn là mất chỗ để soi chuyện thứ hai. */
t( 'và KHÔNG mang nhãn phá khoá',
	null !== $_vet_th && strpos( (string) $_vet_th['hanhDong'], 'đã khóa' ) === false, $_vet_th );
t( 'vết ghi kèm số tiền của dòng đã mất',
	null !== $_vet_th && preg_match( '/11[.,]?000/', (string) $_vet_th['chiTiet'] ) === 1, $_vet_th );

VHCP_Auth::dat_vai_tro( $_vt_truoc );

/* XÓA VĨNH VIỄN CẢ ĐƠN bằng quyền Admin cũng phải để lại vết — sau khi xóa thì đơn, dòng chi
   phí và tạm ứng đều biến mất, nhật ký là bản sao duy nhất còn lại để đối chiếu. */
$_ma_xoa = VHCP_Don::create_don( 'T9/2026 (01/9-07/9/2026)', 'Nguyễn Văn A' )['maDon'];
VHCP_Don::add_line( $_ma_xoa, array( 'coso' => 'FARM PHAN THIẾT', 'ngay' => $today, 'phanLoaiTT' => 'Thanh toán cá nhân', 'nhom' => 'Nguyên vật liệu', 'noiDung' => 'Đơn sắp bị xóa', 'soLuong' => 1, 'donGia' => 555000, 'thanhTien' => 555000 ) );
VHCP_Auth::dat_vai_tro( 'Admin' );
t( 'Admin xóa vĩnh viễn cả đơn', ! empty( VHCP_Don::delete_don_admin( $_ma_xoa )['success'] ) );
t( 'đơn đã biến mất', null === VHCP_Don::don_row( $_ma_xoa ) );
$_it2 = VHCP_Log::get_log( array( 'limit' => 200 ) )['items'];
$_vet2 = false;
foreach ( $_it2 as $_x ) {
	if ( strpos( (string) $_x['hanhDong'], 'XÓA VĨNH VIỄN cả đơn' ) !== false
		&& (string) $_x['doiTuong'] === $_ma_xoa
		&& strpos( (string) $_x['chiTiet'], '1 dòng' ) !== false
		&& preg_match( '/555[.,]?000/', (string) $_x['chiTiet'] ) ) { $_vet2 = true; break; }
}
t( 'xóa cả đơn có ghi nhật ký kèm số dòng và tổng tiền', $_vet2 );
VHCP_Auth::dat_vai_tro( $_vt_truoc );
$l4 = VHCP_Don::add_line( $ma, array( 'coso' => 'FARM PHAN THIẾT', 'ngay' => $today, 'phanLoaiTT' => 'Thanh toán cá nhân', 'nhom' => 'Phát sinh', 'noiDung' => 'Mua thêm đá', 'thanhTien' => 50000 ) );

// nhập thực chi
t( 'nhập thực chi dòng 1', ! empty( VHCP_Don::set_line_thuc_mua( $l1['id'], 1150000, 'Nguyễn Văn A' )['success'] ) );
VHCP_Don::set_line_thuc_mua( $l2['id'], 300000, 'Nguyễn Văn A' );
VHCP_Don::set_line_thuc_mua( $l3['id'], 300000, 'Nguyễn Văn A' );
VHCP_Don::set_line_thuc_mua( $l4['id'], 50000, 'Nguyễn Văn A' );
$g = VHCP_Don::get_don( $ma );
teq( 'đã chi cá nhân sau thực chi', 1500000, $g['tongCN']['thucChi'] );   // 1.150.000 + 300.000 + 50.000
teq( 'tạm ứng cục giữ nguyên', 1700000, $g['tongCN']['tamUng'] );
teq( 'chênh lệch = thừa 200.000', 200000, $g['tongCN']['chenhLech'] );

t( 'gửi quyết toán', ! empty( VHCP_Don::gui_quyet_toan( $ma )['success'] ) );
$before = VHCP_Don::don_row( $ma )['ghi_chu'];
VHCP_Don::set_line_thuc_mua( $l1['id'], 1100000, 'Lê Kế Toán' );
t( 'kế toán sửa số khi Chờ quyết toán -> gắn cờ [KT sửa]', strpos( VHCP_Don::don_row( $ma )['ghi_chu'], '[KT sửa]' ) !== false, VHCP_Don::don_row( $ma )['ghi_chu'] );

$g = VHCP_Don::get_don( $ma );
teq( 'chênh lệch sau khi KT sửa', 250000, $g['tongCN']['chenhLech'] );
$b = VHCP_Don::xac_nhan_qt_cn_nhieu( array( $ma ), 'Lê Kế Toán' );
t( 'duyệt quyết toán theo lô', ! empty( $b['success'] ) && $b['done'] === 1, $b );
$row = VHCP_Don::don_row( $ma );
teq( 'trạng thái sau quyết toán', 'Đã quyết toán', $row['trang_thai'] );
teq( 'xử lý = NV trả lại', 'NV trả lại', $row['xu_ly'] );
teq( 'chênh lệch lưu vào đơn', 250000, (float) $row['chenh_lech_qt'] );

// danh sách đơn
$dons = VHCP_Don::list_dons();
teq( 'danh sách có 2 đơn (FARM + TÀU TÂN PHÚ)', 2, count( $dons ) );
$d_farm = null; $d_tau = null;
foreach ( $dons as $x ) { if ( $x['maDon'] === $ma ) { $d_farm = $x; } if ( $x['maDon'] === $ma_ncc ) { $d_tau = $x; } }
teq( 'cơ sở của đơn FARM', 'FARM PHAN THIẾT', $d_farm['coso'] );
teq( 'cơ sở của đơn TÀU TÂN PHÚ', 'TÀU TÂN PHÚ', $d_tau['coso'] );
teq( 'thực chi cá nhân trên danh sách', 1450000, $d_farm['thucChiCN'] );
teq( 'thực chi NCC trên danh sách', 300000, $d_tau['thucChiNCC'] );

// BÙ TRỪ LUÂN CHUYỂN: kỳ này tự lấy phần dư/thiếu của kỳ TRƯỚC của CHÍNH người đó.
// Đơn trên: tạm ứng 2.000.000 − thực chi cá nhân 1.450.000 = DƯ 550.000 -> kỳ sau trừ đi.
$d2 = VHCP_Don::create_don( 'T8/2026 (24/8-30/8/2026)', 'Nguyễn Văn A' );
$ma2 = $d2['maDon'];
$g2  = VHCP_Don::get_don( $ma2 );
teq( 'kỳ trước DƯ thì kỳ này trừ đi', -250000, VHCP_Util::num( $g2['don']['buTru'] ) );
// Luật mới gộp CẢ TUẦN của quản lý, nên không còn khái niệm "một đơn kỳ trước".
teq( 'bù trừ quy về QUẢN LÝ duyệt, không phải người lập', 'Trần Quản Lý', (string) $g2['don']['buTruAuto']['quanLy'] );
teq( 'gộp đúng số đơn của tuần trước', 1, (int) $g2['don']['buTruAuto']['soDon'] );
t( 'đơn chưa duyệt thì đánh dấu là quản lý DỰ KIẾN', ! empty( $g2['don']['buTruAuto']['duKien'] ), $g2['don']['buTruAuto'] );
t( 'ghi rõ lý do là còn DƯ', strpos( (string) $g2['don']['buTruAuto']['lyDo'], 'DƯ' ) !== false, $g2['don']['buTruAuto']['lyDo'] );
teq( 'số ghi thẳng vào đơn, không chờ giao diện', -250000, (float) VHCP_Don::don_row( $ma2 )['bu_tru'] );

// Người khác thì không ăn theo bù trừ của người này
$d3 = VHCP_Don::create_don( 'T8/2026 (24/8-30/8/2026)', 'Người Mới Toanh' );
teq( 'người khác không bị bù trừ của người này', 0, VHCP_Util::num( VHCP_Don::get_don( $d3['maDon'] )['don']['buTru'] ) );

// Kỳ trước đã TẤT TOÁN = đã thu/bù xong bằng tiền -> không còn gì luân chuyển
t( 'đánh dấu tất toán kỳ trước', ! empty( VHCP_Don::set_tat_toan_tuan( $ma, true, 'Lê Kế Toán' )['success'] ) );
$g2b = VHCP_Don::get_don( $ma2 );
teq( 'tất toán rồi thì bù trừ về 0', 0, VHCP_Util::num( $g2b['don']['buTru'] ) );
t( 'và nói rõ vì sao', strpos( (string) $g2b['don']['buTruAuto']['lyDo'], 'tất toán' ) !== false, $g2b['don']['buTruAuto']['lyDo'] );
VHCP_Don::set_tat_toan_tuan( $ma, false, 'Lê Kế Toán' );
VHCP_Don::delete_don_admin( $ma2 );
VHCP_Don::delete_don_admin( $d3['maDon'] );

// gợi ý sản phẩm
$prod = VHCP_Don::product_suggestions();
$found = false;
foreach ( $prod as $p ) { if ( $p['ten'] === 'Thịt heo' ) { $found = ( (int) $p['gia'] === 120000 && $p['dvt'] === 'kg' ); } }
t( 'gợi ý sản phẩm nhớ đơn giá + ĐVT', $found );

// ---------------------------------------------------------------- 4. báo cáo tổng quan
$fr = VHCP_Report::finance( array() );
teq( 'tổng xin (không tính phát sinh)', 1800000, $fr['totals']['xin'] );
teq( 'tổng thực tế', 1750000, $fr['totals']['thucTe'] );
teq( 'dự phòng vào báo cáo', 200000, $fr['totals']['duPhong'] );
teq( 'bù trừ vào báo cáo', 0, $fr['totals']['buTru'] );
teq( 'số đơn', 2, $fr['totals']['soDon'] );
$fr2 = VHCP_Report::finance( array( 'coso' => 'TÀU TÂN PHÚ' ) );
teq( 'lọc theo cơ sở', 300000, $fr2['totals']['thucTe'] );

// ---------------------------------------------------------------- 5. xuất MISA đơn vận hành
// Đơn TÀU TÂN PHÚ (dòng NCC) cũng phải đi hết quy trình mới xuất MISA được — mỗi đơn 1 cơ
// sở nên nó là đơn riêng, không còn đi kèm đơn FARM như trước.
VHCP_Don::gui_duyet_tam_ung( $ma_ncc );
VHCP_Don::duyet_tam_ung( $ma_ncc, 'Trần Quản Lý', '' );
VHCP_Don::cap_tam_ung( $ma_ncc, 'Lê Kế Toán', 'Tiền mặt' );
VHCP_Don::gui_quyet_toan( $ma_ncc );
VHCP_Don::xac_nhan_qt_cn_nhieu( array( $ma_ncc ), 'Lê Kế Toán' );
teq( 'đơn NCC đã quyết toán', 'Đã quyết toán', VHCP_Don::don_row( $ma_ncc )['trang_thai'] );

VHCP_Cfg::save_config( array(
	'coso' => array(
		array( 'ten' => 'FARM PHAN THIẾT', 'maDonVi' => 'FARM_PT', 'phanLoaiLon' => 'FARM', 'tenMisa' => 'Farm Phan Thiết' ),
		array( 'ten' => 'TÀU TÂN PHÚ', 'maDonVi' => 'TAU_TP', 'phanLoaiLon' => 'TAU', 'tenMisa' => 'Tàu Tân Phú' ),
	),
	'tkNoMatrix' => array(
		array( 'nhom' => 'NVL đồ ăn - Mua lẻ', 'pll' => 'FARM', 'tkNo' => '6421' ),
		array( 'nhom' => 'Chi phí cơ sở', 'pll' => 'FARM', 'tkNo' => '64127' ),
		array( 'nhom' => 'SP Đồ uống - NCC', 'pll' => 'TAU', 'tkNo' => '1561' ),
		array( 'nhom' => 'Phát sinh', 'pll' => 'FARM', 'tkNo' => '64128' ),
	),
	'users' => array(
		array( 'ten' => 'Admin', 'pin' => '1111', 'vaiTro' => 'Admin' ),
		array( 'ten' => 'Trần Quản Lý', 'pin' => '2222', 'vaiTro' => 'Quản lý', 'tkCo' => '3341', 'maDt' => 'NV_QL' ),
	),
) );
$ex = VHCP_Misa::export_misa( 'all', 'chuaxuat', 'all' );
teq( 'MISA: 10 cột', 10, count( $ex['cols'] ) );
teq( 'MISA: 4 dòng hạch toán', 4, $ex['count'] );
teq( 'MISA: 2 đơn (FARM + TÀU TÂN PHÚ)', 2, $ex['sodon'] );
$sum = 0;
foreach ( $ex['rows'] as $rw ) { $sum += $rw[7]; }
teq( 'MISA: tổng tiền = 1.750.000', 1750000, $sum );
$r0 = $ex['rows'][0];
teq( 'MISA: TK Có theo người duyệt tạm ứng', '3341', $r0[6] );
teq( 'MISA: mã đối tượng theo người duyệt', 'NV_QL', $r0[8] );
t( 'MISA: diễn giải có kỳ + tên người duyệt', strpos( $r0[3], 'Trần Quản Lý' ) !== false, $r0[3] );
$tk_no = array();
foreach ( $ex['rows'] as $rw ) { $tk_no[ $rw[5] ] = 1; }
t( 'MISA: TK Nợ lấy từ ma trận nhóm × phân loại lớn', isset( $tk_no['6421'] ) && isset( $tk_no['1561'] ), array_keys( $tk_no ) );
teq( 'MISA: không còn cảnh báo thiếu cấu hình', array(), $ex['warn'] );

// TK NỢ PHẢI LÀ TÀI KHOẢN CỦA LOẠI CHI PHÍ, KHÔNG PHẢI MÃ CŨ TRÊN DÒNG.
// Dòng nhập từ thời chưa có danh mục loại chi phí mang sẵn tk_no = 141 (tạm ứng). Trước
// đây mã trên dòng được ưu tiên nên bảng xuất ra "Nợ 141 · Có 141" — vô nghĩa, vì 141 là
// tài khoản của BÊN TRẢ TIỀN, chỉ được đứng ở cột Có.
global $wpdb;
$t_cp = VHCP_DB::t( 'chiphi' );
$n_bam = 0;
foreach ( VHCP_Don::cp_rows() as $rr ) {
	$wpdb->update( $t_cp, array( 'tk_no' => '141' ), array( 'id' => (string) $rr['id'] ) );
	$n_bam++;
}
t( 'bối cảnh: đã bơm tk_no=141 vào mọi dòng chi', $n_bam >= 4, $n_bam );
$ex141 = VHCP_Misa::export_misa( 'all', 'chuaxuat', 'all' );
$no141 = array();
foreach ( $ex141['rows'] as $rw ) { $no141[ $rw[5] ] = 1; }
t( 'MISA: mã 141 trên dòng KHÔNG được đè lên TK Nợ', ! isset( $no141['141'] ), array_keys( $no141 ) );
t( 'MISA: TK Nợ vẫn lấy đúng theo loại chi phí', isset( $no141['6421'] ) && isset( $no141['1561'] ), array_keys( $no141 ) );
teq( 'MISA: số dòng không đổi sau khi bơm mã cũ', $ex['count'], $ex141['count'] );

// Nhóm còn đuôi "- NCC" / "- Mua lẻ" vẫn phải tra ra mã của loại chi phí (đuôi chỉ để lọc).
$loai_goc = VHCP_Cfg::cfg_static()['loaiChiPhi'];
$loai_tam = $loai_goc;
$loai_tam[] = array( 'ten' => 'NVL đồ ăn', 'tkNo' => '6423', 'tkCo' => '', 'maDt' => '', 'boPhan' => '', 'note' => '', 'tenMisa' => '', 'loaiTt' => '' );
VHCP_Cfg::save_config( array( 'loaiChiPhi' => $loai_tam ) );
teq( 'tkno_loai: bỏ đuôi "- Mua lẻ" rồi mới tra danh mục', '6423', VHCP_Cfg::tkno_loai( 'NVL đồ ăn - Mua lẻ', '' ) );
teq( 'tkno_loai: ma trận theo cơ sở thắng danh mục', '6421', VHCP_Cfg::tkno_loai( 'NVL đồ ăn - Mua lẻ', 'FARM PHAN THIẾT' ) );
teq( 'tkno_loai: chưa khai thì trả rỗng (không đoán)', '', VHCP_Cfg::tkno_loai( 'Nhóm chưa khai bao giờ', '' ) );
t( 'la_tk_ben_tra: 141/331 là tài khoản bên trả tiền', VHCP_Cfg::la_tk_ben_tra( '141' ) && VHCP_Cfg::la_tk_ben_tra( '3311' ) );
t( 'la_tk_ben_tra: tài khoản chi phí thì không', ! VHCP_Cfg::la_tk_ben_tra( '6421' ) && ! VHCP_Cfg::la_tk_ben_tra( '' ) );

// NGÀY VÔ LÝ phải được báo trước khi tệp sang MISA (bảng xuất từng ra "22/08/4622").
t( 'ngày vô lý: năm 4622', VHCP_Util::ngay_vo_ly( '22/08/4622' ) );
t( 'ngày vô lý: 31/02', VHCP_Util::ngay_vo_ly( '31/02/2026' ) );
t( 'ngày vô lý: năm 1899', VHCP_Util::ngay_vo_ly( '22/08/1899' ) );
t( 'ngày thường thì KHÔNG báo', ! VHCP_Util::ngay_vo_ly( '22/08/2026' ) && ! VHCP_Util::ngay_vo_ly( '' ) );
$id_ngay = null;
foreach ( VHCP_Don::cp_rows() as $rr ) { $id_ngay = (string) $rr['id']; break; }
$wpdb->update( $t_cp, array( 'ngay' => '4622-08-22' ), array( 'id' => $id_ngay ) );
$ex_ng = VHCP_Misa::export_misa( 'all', 'chuaxuat', 'all' );
$co_bao = false;
foreach ( $ex_ng['warn'] as $w ) { if ( strpos( $w, '4622' ) !== false ) { $co_bao = true; } }
t( 'MISA: báo ngày vô lý ra ô cảnh báo', $co_bao, $ex_ng['warn'] );
$wpdb->update( $t_cp, array( 'ngay' => VHCP_Util::today_sql() ), array( 'id' => $id_ngay ) );

// trả lại danh mục + mã đúng cho các phép thử sau
VHCP_Cfg::save_config( array( 'loaiChiPhi' => $loai_goc ) );
VHCP_Don::gan_ma_tai_khoan( true );

teq( 'MISA lọc NCC: chưa duyệt NCC thì không xuất', 0, VHCP_Misa::export_misa( 'all', 'chuaxuat', 'ncc' )['count'] );
t( 'kế toán NCC duyệt độc lập', ! empty( VHCP_Don::xac_nhan_quyet_toan_ncc( $ma_ncc, 'Phạm KT NCC' )['success'] ) );
$exn = VHCP_Misa::export_misa( 'all', 'chuaxuat', 'ncc' );
teq( 'MISA lọc NCC: 1 dòng', 1, $exn['count'] );
// App cũ: TK Có LUÔN ưu tiên TK Có của người duyệt tạm ứng, chỉ khi người đó chưa
// cấu hình TK Có thì mới rơi về TK Có của phân loại (141 cá nhân / 331 NCC).
teq( 'MISA lọc NCC: TK Có = TK Có người duyệt (ưu tiên hơn 331)', '3341', $exn['rows'][0][6] );
teq( 'MISA lọc NCC: TK Nợ theo ma trận', '1561', $exn['rows'][0][5] );
teq( 'MISA lọc cá nhân: 3 dòng', 3, VHCP_Misa::export_misa( 'all', 'chuaxuat', 'cn' )['count'] );

// Người duyệt CHƯA cấu hình TK Có -> rơi về TK Có của phân loại (141 / 331).
VHCP_Cfg::save_config( array( 'users' => array(
	array( 'ten' => 'Admin', 'pin' => '1111', 'vaiTro' => 'Admin' ),
	array( 'ten' => 'Trần Quản Lý', 'pin' => '2222', 'vaiTro' => 'Quản lý' ),
) ) );
$exf = VHCP_Misa::export_misa( 'all', 'chuaxuat', 'ncc' );
teq( 'MISA: fallback TK Có 331 cho dòng NCC', '331', $exf['rows'][0][6] );
$exf2 = VHCP_Misa::export_misa( 'all', 'chuaxuat', 'cn' );
teq( 'MISA: fallback TK Có 141 cho dòng cá nhân', '141', $exf2['rows'][0][6] );
VHCP_Cfg::save_config( array( 'users' => array(
	array( 'ten' => 'Admin', 'pin' => '1111', 'vaiTro' => 'Admin' ),
	array( 'ten' => 'Trần Quản Lý', 'pin' => '2222', 'vaiTro' => 'Quản lý', 'tkCo' => '3341', 'maDt' => 'NV_QL' ),
) ) );

// PIN / mã số nạp từ bảng tính hay mang đuôi ".0" ("2222.0", "141.0"). PIN kiểu đó không
// khớp luật 4–8 chữ số nên người đó KHÔNG đăng nhập được, mà bảng vẫn thấy PIN nằm đó.
teq( 'bỏ đuôi .0 của mã số', '141', VHCP_Util::ma_so( '141.0' ) );
teq( 'không cắt số thập phân thật', '1.5', VHCP_Util::ma_so( '1.5' ) );
teq( 'PIN chỉ còn chữ số', '2222', VHCP_Util::pin_sach( '2222.0' ) );
VHCP_Cfg::save_config( array( 'users' => array(
	array( 'ten' => 'Admin', 'pin' => '1111', 'vaiTro' => 'Admin' ),
	array( 'ten' => 'Kế Toán Đuôi Chấm', 'pin' => '3333.0', 'vaiTro' => 'Quản lý', 'tkCo' => '141.0', 'maDt' => 'NV0021' ),
) ) );
$lg_pin = VHCP_Auth::login( '3333' );
t( 'PIN có đuôi .0 vẫn đăng nhập được', ! empty( $lg_pin['ok'] ) );
$u_pin = null;
foreach ( VHCP_Cfg::get_users() as $u ) { if ( $u['ten'] === 'Kế Toán Đuôi Chấm' ) { $u_pin = $u; } }
teq( 'PIN lưu lại đã sạch', '3333', $u_pin['pin'] );
teq( 'TK Có cũng bỏ đuôi .0', '141', $u_pin['tkCo'] );
VHCP_Cfg::save_config( array( 'users' => array(
	array( 'ten' => 'Admin', 'pin' => '1111', 'vaiTro' => 'Admin' ),
	array( 'ten' => 'Trần Quản Lý', 'pin' => '2222', 'vaiTro' => 'Quản lý', 'tkCo' => '3341', 'maDt' => 'NV_QL' ),
) ) );

t( 'chốt đã xuất', ! empty( VHCP_Misa::mark_exported( array( $ma, $ma_ncc ), 'all' )['success'] ) );
teq( 'trạng thái sau chốt xuất', 'Đã xuất MISA', VHCP_Don::don_row( $ma )['trang_thai'] );
teq( 'xuất lại lần 2 không còn đơn nào', 0, VHCP_Misa::export_misa( 'all', 'chuaxuat', 'all' )['count'] );
teq( 'lọc mode đã xuất thì thấy lại', 4, VHCP_Misa::export_misa( 'all', 'daxuat', 'all' )['count'] );

// ------------------------------------- 5b. ĐƠN ĐÃ CHỐT SỔ VẪN BỔ SUNG ĐƯỢC HÓA ĐƠN
/* Anh Thắng: *"đối với đơn đã xuất MISA hoặc đã quyết toán, kế toán được phép bổ sung thêm hóa
   đơn nếu thiếu hoặc sai"* và *"với 1 đơn cho phép upload 2 hóa đơn"*.

   Hóa đơn giấy về sau ngày chốt sổ là chuyện thường, và hóa đơn SAI thì phải thay được — không
   thì bộ chứng từ vĩnh viễn thiếu trong khi số tiền đã đúng rồi. Nhưng nới chỗ này là nới trên
   một đơn ĐÃ SANG SỔ MISA, nên phải chặt: chỉ ẢNH, chỉ KẾ TOÁN, và có vết trong nhật ký. */
$_dong_ma = VHCP_Don::get_don( $ma )['lines'];
$_id_dong = $_dong_ma ? (string) $_dong_ma[0]['id'] : '';
t( 'có dòng để thử đính hóa đơn', '' !== $_id_dong );
teq( 'đơn đang ở trạng thái đã xuất MISA', 'Đã xuất MISA', VHCP_Don::don_row( $ma )['trang_thai'] );

/* 🔴 NHÂN VIÊN KHÔNG ĐƯỢC SỜ vào đơn đã chốt sổ. */
VHCP_Auth::dat_vai_tro( 'Nhân viên' );
$_r = VHCP_Don::set_line_anh( $_id_dong, 'https://x/nv.jpg' );
t( 'nhân viên KHÔNG đính được hóa đơn vào đơn đã chốt sổ', empty( $_r['success'] ), $_r );
t( 'và được nói rõ vì sao, chứ không phải câu chung chung',
	isset( $_r['error'] ) && strpos( $_r['error'], 'chỉ KẾ TOÁN' ) !== false, $_r );
$_sau = VHCP_Don::line_row( $_id_dong );
teq( 'ảnh dòng không hề đổi', '', (string) $_sau['anh'] );

/* Kế toán thì được — đó là cả điểm của việc này. */
foreach ( array( 'Kế toán cá nhân', 'Kế toán NCC', 'Admin' ) as $_vt_kt ) {
	VHCP_Auth::dat_vai_tro( $_vt_kt );
	$_r = VHCP_Don::set_line_anh( $_id_dong, 'https://x/' . rawurlencode( $_vt_kt ) . '.jpg' );
	t( "$_vt_kt bổ sung được hóa đơn dòng khi đơn đã chốt sổ", ! empty( $_r['success'] ), $_r );
}
$_sau = VHCP_Don::line_row( $_id_dong );
t( 'ảnh dòng đã ghi thật', strpos( (string) $_sau['anh'], 'https://x/' ) === 0, $_sau['anh'] );

/* 🔴 CHỈ ẢNH — đường này không được đụng một con số nào. Đơn đã sang sổ MISA mà số đổi được là
   hai bên sổ lệch nhau, và không ai biết vì bút toán đã xuất đi rồi. */
$_truoc_tien = (string) $_sau['thanh_tien'];
VHCP_Don::set_line_anh( $_id_dong, 'https://x/khac.jpg' );
$_sau2 = VHCP_Don::line_row( $_id_dong );
teq( 'thành tiền KHÔNG đổi theo', $_truoc_tien, (string) $_sau2['thanh_tien'] );
teq( 'thực mua KHÔNG đổi theo', (string) $_sau['thuc_mua'], (string) $_sau2['thuc_mua'] );
teq( 'mã tài khoản KHÔNG đổi theo', (string) $_sau['tk_no'], (string) $_sau2['tk_no'] );
teq( 'trạng thái đơn vẫn là đã xuất MISA', 'Đã xuất MISA', VHCP_Don::don_row( $ma )['trang_thai'] );

/* Đụng vào đơn đã chốt sổ thì PHẢI để lại vết — người đối chiếu sau này cần biết ai đổi, lúc nào. */
$_nk = VHCP_Log::get_log( array( 'limit' => 200 ) )['items'];
$_co_vet = false;
foreach ( (array) $_nk as $_x ) {
	if ( strpos( (string) $_x['hanhDong'], 'hóa đơn dòng (đơn đã chốt)' ) !== false ) { $_co_vet = true; }
}
t( 'có vết trong nhật ký khi đụng đơn đã chốt sổ', $_co_vet );

/* --- HAI Ô HÓA ĐƠN CẤP ĐƠN --- */
VHCP_Auth::dat_vai_tro( 'Kế toán cá nhân' );
$_r1 = VHCP_Don::set_hoa_don_qt( $ma, 'https://x/hd1.jpg', 'KT', 1 );
$_r2 = VHCP_Don::set_hoa_don_qt( $ma, 'https://x/hd2.jpg', 'KT', 2 );
t( 'đính được hóa đơn ô 1', ! empty( $_r1['success'] ), $_r1 );
t( 'đính được hóa đơn ô 2', ! empty( $_r2['success'] ), $_r2 );
$_d = VHCP_Don::get_don( $ma )['don'];
teq( 'ô 1 giữ đúng ảnh của nó', 'https://x/hd1.jpg', (string) $_d['anhHoaDon'] );
teq( 'ô 2 giữ đúng ảnh của nó', 'https://x/hd2.jpg', (string) $_d['anhHoaDon2'] );
/* 🔴 Hai ô phải ĐỘC LẬP. Đính ô 2 mà đè mất ô 1 thì "hai hóa đơn" chỉ là cái tên. */
VHCP_Don::set_hoa_don_qt( $ma, 'https://x/hd2-moi.jpg', 'KT', 2 );
$_d = VHCP_Don::get_don( $ma )['don'];
teq( 'đổi ô 2 KHÔNG đụng ô 1', 'https://x/hd1.jpg', (string) $_d['anhHoaDon'] );
teq( 'và ô 2 nhận ảnh mới', 'https://x/hd2-moi.jpg', (string) $_d['anhHoaDon2'] );
/* Số ô lạ thì về ô 1, không ghi bừa ra chỗ thứ ba. */
VHCP_Don::set_hoa_don_qt( $ma, 'https://x/hd-la.jpg', 'KT', 9 );
$_d = VHCP_Don::get_don( $ma )['don'];
teq( 'số ô lạ về ô 1, không sinh ô thứ ba', 'https://x/hd-la.jpg', (string) $_d['anhHoaDon'] );
teq( 'ô 2 vẫn nguyên', 'https://x/hd2-moi.jpg', (string) $_d['anhHoaDon2'] );
VHCP_Auth::dat_vai_tro( 'Admin' );

// ---------------------------------------------------------------- 6. chi phí kỹ thuật
$da = VHCP_DuAn::create_du_an( 'Tháo dỡ', 'TÀU GÒ VẤP', 'Kỹ thuật viên' );
$md = $da['maDA'];
t( 'tạo dự án tháo dỡ', ! empty( $da['success'] ) );
VHCP_DuAn::add_line( $md, array( 'noiDung' => 'Tháo khung sắt', 'duToan' => 10000000, 'hinhThuc' => 'Tạm ứng', 'gian' => 'TÀU GÒ VẤP' ) );
VHCP_DuAn::add_line( $md, array( 'noiDung' => 'Nhân công', 'thucTe' => 6000000, 'capCha' => 'Tháo khung sắt', 'gian' => 'TÀU GÒ VẤP' ) );
VHCP_DuAn::add_line( $md, array( 'noiDung' => 'Xe cẩu', 'thucTe' => 3000000, 'capCha' => 'Tháo khung sắt', 'gian' => 'TÀU GÒ VẤP' ) );
VHCP_DuAn::add_line( $md, array( 'noiDung' => 'Thuê kho', 'duToan' => 2000000, 'thucTe' => 2500000, 'hinhThuc' => 'Trực tiếp', 'vat' => 'Có VAT', 'gian' => 'TÀU GÒ VẤP' ) );
$gd = VHCP_DuAn::get_du_an( $md );
teq( 'dự án: tổng dự toán', 12000000, $gd['tongDuToan'] );
teq( 'dự án: tổng thực tế (mục con + dòng lớn không con)', 11500000, $gd['tongThucTe'] );
// Tách rõ phần đến từ sổ chi phí, để màn dự án ghi được "hạng mục X + sổ chi phí Y"
teq( 'dự án: chưa có dòng sổ chi phí thì phần đó bằng 0', 0, VHCP_Util::num( $gd['tongSoChi'] ) );
teq( 'dự án: dự toán từ sổ chi phí cũng bằng 0', 0, VHCP_Util::num( $gd['duToanSoChi'] ) );
teq( 'dự án: cần tạm ứng (dự toán hình thức Tạm ứng)', 10000000, $gd['canTamUng'] );
teq( 'dự án: trả trực tiếp (dự toán)', 2000000, $gd['traTrucTiep'] );
teq( 'dự án: thực tế tạm ứng', 9000000, $gd['ttTamUng'] );
teq( 'dự án: thực tế trực tiếp', 2500000, $gd['ttTrucTiep'] );
teq( 'dự án: trực tiếp có VAT', 2500000, $gd['ttTrucTiepVAT'] );
teq( 'mục con thừa hưởng hình thức chi của cha', 'Tạm ứng', $gd['lines'][1]['hinhThuc'] );

t( 'gửi kế toán duyệt', ! empty( VHCP_DuAn::submit( $md )['success'] ) );
// Dự án chi trực tiếp: chỉ ĐÃ ĐÓNG mới khóa, các trạng thái khác vẫn nhập được
t( 'chưa đóng thì vẫn nhập được', ! empty( VHCP_DuAn::add_line( $md, array( 'noiDung' => 'X ghi được' ) )['success'] ) );
t( 'xóa lại dòng vừa thêm', ! empty( VHCP_DuAn::delete_line( $md, VHCP_DuAn::get_du_an( $md )['lines'][ count( VHCP_DuAn::get_du_an( $md )['lines'] ) - 1 ]['row'] )['success'] ) );
$pend = VHCP_Report::pending_modules();
teq( 'gom đơn chờ kế toán: 1', 1, count( $pend['items'] ) );
teq( 'gom đơn chờ kế toán: đúng số tiền thực tế', 11500000, $pend['items'][0]['soTien'] );
t( 'kế toán duyệt dự án', ! empty( VHCP_DuAn::approve( $md, 'Lê Kế Toán' )['success'] ) );
t( 'duyệt xong mới chi tiền được', ! empty( VHCP_DuAn::confirm_pay( $md, 'tamUng', 'tu', 10000000, 'Lê Kế Toán' )['success'] ) );

// xóa hạng mục lớn -> mục con thành (Phát sinh), không mất tiền
$row_parent = $gd['lines'][0]['row'];
t( 'xóa hạng mục lớn', ! empty( VHCP_DuAn::delete_line( $md, $row_parent )['success'] ) );
$gd2 = VHCP_DuAn::get_du_an( $md );
teq( 'sau khi xóa cha: tổng thực tế không đổi', 11500000, $gd2['tongThucTe'] );
$caps = array();
foreach ( $gd2['lines'] as $l ) { $caps[ $l['capCha'] ] = 1; }
t( 'mục con chuyển sang (Phát sinh)', isset( $caps['(Phát sinh)'] ), array_keys( $caps ) );

$exk = VHCP_Misa::export_ky_thuat();
teq( 'MISA kỹ thuật: 3 dòng có thực tế', 3, $exk['count'] );
$tkco = array();
foreach ( $exk['rows'] as $rw ) { $tkco[ $rw[6] ] = ( isset( $tkco[ $rw[6] ] ) ? $tkco[ $rw[6] ] : 0 ) + $rw[7]; }
teq( 'MISA kỹ thuật: 331 cho trực tiếp', 2500000, isset( $tkco['331'] ) ? $tkco['331'] : 0 );
teq( 'MISA kỹ thuật: 64125 cho tạm ứng', 9000000, isset( $tkco['64125'] ) ? $tkco['64125'] : 0 );

// chi phí cơ sở chung: gọi 2 lần phải ra CÙNG 1 sheet
$c1 = VHCP_DuAn::create_du_an( 'Chi phí cơ sở', '', 'Kỹ thuật viên' );
$c2 = VHCP_DuAn::create_du_an( 'Chi phí cơ sở', '', 'Người khác' );
teq( 'chi phí cơ sở chung chỉ 1 bản', $c1['maDA'], $c2['maDA'] );
t( 'không xóa được chi phí cơ sở chung', empty( VHCP_DuAn::delete( $c1['maDA'] )['success'] ) );
t( 'không đổi tên chi phí cơ sở chung', empty( VHCP_DuAn::rename_du_an( $c1['maDA'], 'X' )['success'] ) );

// ---------------------------------------------------------------- 7. marketing
$mk = VHCP_MK::create_don( 'FARM PHAN THIẾT', 'Hội chợ hè', '08/2026', 'Facebook', 'MKT' );
$mma = $mk['ma'];
VHCP_MK::add_line( $mma, array( 'kenh' => 'Facebook', 'noiDung' => 'Chạy ads', 'duToan' => 5000000, 'thucTe' => 4800000, 'hinhThuc' => 'Trực tiếp', 'vat' => 'Có VAT', 'ketQua' => 120, 'ngay' => $today ) );
VHCP_MK::add_line( $mma, array( 'kenh' => 'Hoạt náo', 'noiDung' => 'Thuê MC', 'duToan' => 2000000, 'thucTe' => 2000000, 'hinhThuc' => 'Tạm ứng', 'ketQua' => 0, 'ngay' => $today ) );
$gm = VHCP_MK::get_don( $mma );
teq( 'marketing: tổng thực chi', 6800000, $gm['tongThucChi'] );
teq( 'marketing: chi phí / kết quả', 56667, $gm['cpKetQua'] );
teq( 'marketing: trực tiếp có VAT', 4800000, $gm['thucTrucTiepVAT'] );
$exm = VHCP_Misa::export_marketing();
teq( 'MISA marketing: 2 dòng', 2, $exm['count'] );
teq( 'MISA marketing: mã đơn vị theo cơ sở', 'FARM_PT', $exm['rows'][0][9] );

// ---------------------------------------------------------------- 8. công tác / setup
$bp = VHCP_BP::create( 'Công tác', 'Đi Nha Trang', 'Nguyễn Văn A', 'EVENT FARM NHA TRANG', '08/2026', 'Admin' );
$bma = $bp['ma'];
VHCP_BP::add_line( $bma, array( 'noiDung' => 'Vé xe', 'soLuong' => 2, 'donGia' => 300000, 'duToan' => 600000, 'thucTe' => 640000, 'hinhThuc' => 'Tạm ứng', 'ngay' => $today ) );
VHCP_BP::add_line( $bma, array( 'noiDung' => 'Khách sạn', 'duToan' => 1200000, 'thucTe' => 1200000, 'hinhThuc' => 'Trực tiếp', 'vat' => 'Có VAT', 'ngay' => $today ) );
$gb = VHCP_BP::get( $bma );
teq( 'công tác: tổng dự toán', 1800000, $gb['tongDuToan'] );
teq( 'công tác: tổng thực chi', 1840000, $gb['tongThucChi'] );
teq( 'công tác: thành tiền tự tính SL × ĐG', 600000, $gb['lines'][0]['thanhTien'] );
$exb = VHCP_Misa::export_bp( 'all' );
teq( 'MISA công tác: 2 dòng', 2, $exb['count'] );
teq( 'MISA công tác: 1 đợt', 1, $exb['sodon'] );

// ---------------------------------------------------------------- 9. vận hành tuần & báo cáo gian
$vh = VHCP_Report::van_hanh_tuan( $today );
$tong_vh = 0;
foreach ( $vh['list'] as $m ) { $tong_vh += $m['tong']; }
t( 'vận hành tuần: có số liệu', $tong_vh > 0, $tong_vh );
teq( 'vận hành tuần: tổng khớp grand', $tong_vh, $vh['grand']['tong'] );
teq( 'vận hành tuần: mảng vận hành = thực chi các dòng đơn', 1750000, $vh['grand']['vh'] );
teq( 'vận hành tuần: mảng marketing', 6800000, $vh['grand']['mkt'] );
t( 'vận hành tuần: KHÔNG gom công tác vào nữa', ! array_key_exists( 'ct', $vh['grand'] ), array_keys( $vh['grand'] ) );
t( 'vận hành tuần: có thứ 2 và chủ nhật', ! empty( $vh['monday'] ) && ! empty( $vh['sunday'] ) );

$gr = VHCP_Report::gian_report( 'FARM PHAN THIẾT' );
$mods = array();
foreach ( $gr['sections'] as $s ) { $mods[] = $s['module']; }
t( 'báo cáo gian: gom cả marketing và đơn vận hành', count( $mods ) >= 2, $mods );
t( 'báo cáo gian: tổng > 0', $gr['grand'] > 0 );

// ---------------------------------------------------------------- 10. cấu hình: lưu & hồi lại
VHCP_Cfg::save_config( array( 'phanloai' => array( array( 'ten' => 'Thanh toán cá nhân', 'tkCo' => '999' ) ) ) );
teq( 'lưu cấu hình phân loại', '999', VHCP_Cfg::cfg_static()['phanloai'][0]['tkCo'] );
t( 'hồi lại cấu hình', ! empty( VHCP_Cfg::undo_config()['success'] ) );
teq( 'sau khi hồi: 2 phân loại như cũ', 2, count( VHCP_Cfg::cfg_static()['phanloai'] ) );
teq( 'sau khi hồi: TK Có trở lại 141', '141', VHCP_Cfg::cfg_static()['phanloai'][0]['tkCo'] );

// ma trận phân quyền
VHCP_Cfg::set_quyen( array( 'duyetTU' => array( 'Nhân viên' => 1 ) ) );
$q2 = VHCP_Cfg::get_quyen();
t( 'lưu ma trận quyền', $q2['duyetTU']['Nhân viên'] === true && $q2['duyetTU']['Quản lý'] === false );

// đổi PIN
t( 'đổi PIN sai PIN cũ bị chặn', empty( VHCP_Auth::change_pin( 'Admin', '0000', '4321' )['success'] ) );
t( 'đổi PIN thành công', ! empty( VHCP_Auth::change_pin( 'Admin', '1111', '4321' )['success'] ) );
t( 'PIN mới đăng nhập được', ! empty( VHCP_Auth::login( '4321' )['ok'] ) );
t( 'PIN trùng người khác bị chặn', empty( VHCP_Auth::change_pin( 'Admin', '4321', '2222' )['success'] ) );

// ------------------- 10b. SƠ ĐỒ BẢNG TRONG BỘ THỬ KHÔNG ĐƯỢC LỆCH KHỎI DDL THẬT
/* 🔴 wp-stub.php giữ một BẢN SAO GÕ TAY của sơ đồ bảng (dịch sang SQLite). Thêm một cột vào
   class-vhcp-db.php mà quên vá bản sao đó thì bài kiểm chạy trên một cái bảng KHÁC với bảng
   thật — và nó không báo gì cho tới khi có câu UPDATE đụng đúng cột mới, lúc đó chết bằng một
   lỗi SQL khó hiểu ở giữa bài kiểm. Vừa xảy ra đúng như vậy với cột `hoa_don_qt2`.

   Đọc DDL THẬT từ mã nguồn, rút tên cột, đối chiếu với bản sao. KHÔNG so kiểu dữ liệu — hai bên
   khác phương ngữ SQL — chỉ so DANH SÁCH CỘT, đúng thứ hay lệch thật. */
$_stub_src = file_get_contents( __DIR__ . '/wp-stub.php' );
$_db_src   = file_get_contents( dirname( __DIR__, 2 ) . '/wordpress/vhcp-chi-phi/includes/class-vhcp-db.php' );
$_thieu_cot = array();
$_soat_bang = 0;
if ( preg_match_all( '/CREATE TABLE " \. self::t\( \x27([a-z_]+)\x27 \) \. " \((.*?)\) \$c";/s', $_db_src, $_mm, PREG_SET_ORDER ) ) {
	foreach ( $_mm as $_m ) {
		$_ten_b = $_m[1];
		/* Chỉ soát bảng nào bản sao CÓ khai — bảng bộ thử chưa dựng thì không lệch được. */
		if ( ! preg_match( '/CREATE TABLE \{\$p\}' . preg_quote( $_ten_b, '/' ) . ' \((.*?)\)"/s', $_stub_src, $_ms ) ) {
			continue;
		}
		$_soat_bang++;
		foreach ( explode( "\n", $_m[2] ) as $_d ) {
			$_d = trim( $_d );
			if ( '' === $_d || 0 === strpos( $_d, '/*' ) || 0 === strpos( $_d, '*' ) || 0 === strpos( $_d, '//' ) ) { continue; }
			if ( preg_match( '/^(PRIMARY KEY|UNIQUE KEY|KEY)\b/i', $_d ) ) { continue; }
			/* ⚠️ Tên cột CÓ CHỮ SỐ (`hoa_don_qt2`). Biểu thức `[a-z_]+` bỏ sót đúng những cột
			   như vậy — tức là phép soát im lặng bỏ qua đúng thứ nó sinh ra để bắt. Phép phá
			   ngược mới lòi ra chỗ này. */
			if ( ! preg_match( '/^([a-z_][a-z0-9_]*)\s+[A-Z]/', $_d, $_mc ) ) { continue; }
			if ( ! preg_match( '/(^|[,(\s])' . preg_quote( $_mc[1], '/' ) . '\s/', $_ms[1] ) ) {
				$_thieu_cot[] = $_ten_b . '.' . $_mc[1];
			}
		}
	}
}
/* Soát được 0 bảng nghĩa là biểu thức tìm kiếm hỏng, không phải "mọi thứ đều khớp". Hai thứ đó
   trông giống hệt nhau ở kết quả, mà một bên là an toàn còn một bên là phép soát chết lặng. */
t( 'phép soát sơ đồ có thật sự đọc được DDL (không phải khớp vì đọc rỗng)', $_soat_bang >= 3, $_soat_bang );
t( 'bản sao sơ đồ trong wp-stub.php không thiếu cột nào so với DDL thật',
	count( $_thieu_cot ) === 0, implode( ' · ', $_thieu_cot ) );

// ---------------------------------------------------------------- 11. nhật ký
VHCP_Log::log_action( array( 'actor' => 'Admin', 'role' => 'Admin', 'action' => 'Thử nghiệm', 'target' => $ma, 'detail' => 'ghi chú' ) );
$lg = VHCP_Log::get_log( array( 'limit' => 200 ) );
/* ⚠️ TÌM ĐÚNG BẢN GHI VỪA VIẾT, đừng đếm TỔNG nhật ký. Đếm tổng thì thêm bất kỳ việc nào có ghi
   nhật ký ở phần trên là phép thử này vỡ — vỡ vì một thay đổi ĐÚNG, đúng kiểu phép thử dạy người
   ta bỏ qua nó. Tìm đích danh vừa chặt hơn vừa không vỡ oan. */
$_thay_tn = false;
foreach ( (array) $lg['items'] as $_x ) {
	if ( 'Thử nghiệm' === $_x['hanhDong'] && 'ghi chú' === $_x['chiTiet'] ) { $_thay_tn = true; }
}
t( 'nhật ký ghi được', $_thay_tn );
teq( 'nhật ký tìm theo từ khóa', 1, count( VHCP_Log::get_log( array( 'q' => 'thử' ) )['items'] ) );
teq( 'nhật ký tìm từ không có', 0, count( VHCP_Log::get_log( array( 'q' => 'zzz' ) )['items'] ) );

// ---------------------------------------------------------------- 12. tải ảnh
$png = base64_encode( base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DAAAADAAEAAQBhOAAAAABJRU5ErkJggg==' ) );
$up  = VHCP_Upload::upload_image( array( 'base64' => 'data:image/png;base64,' . $png, 'type' => 'image/png' ), $ma, 'FARM PHAN THIẾT' );
t( 'tải ảnh chứng từ', ! empty( $up['success'] ) && strpos( $up['url'], 'FARM' ) !== false, $up );
t( 'chặn file không phải ảnh', empty( VHCP_Upload::upload_image( array( 'base64' => $png, 'type' => 'application/x-php' ), $ma )['success'] ) );
t( 'chặn hồ sơ đuôi .php', empty( VHCP_Upload::upload_doc( array( 'base64' => $png, 'type' => 'application/octet-stream', 'name' => 'shell.php' ), $md )['success'] ) );
t( 'nhận hồ sơ .pdf', ! empty( VHCP_Upload::upload_doc( array( 'base64' => $png, 'type' => 'application/pdf', 'name' => 'hop dong.pdf' ), $md )['success'] ) );

// ---------------------------------------------------------------- 13. nhập CSV
$csv = "ID,Mã đơn,Cơ sở,Ngày,Phân loại TT,Đối tượng,Nhóm mặt hàng,Nội dung,ĐVT,Số lượng,Đơn giá,Thành tiền,Ghi chú,Ảnh,Tạo lúc,Thuế suất (%),Tiền thuế,Thực mua,CN xử lý,Phát sinh\n"
     . "L_import1,{$ma},FARM PHAN THIẾT,15/08/2026,Thanh toán cá nhân,Nguyễn Văn A,\"Chi phí cơ sở\",\"Sơn tường, 2 lớp\",lần,1,\"1.500.000\",\"1.500.000\",,,15/08/2026 09:30:00,,,\"1.450.000\",1,0\n";
$imp = VHCP_Import::run( 'ChiPhi', $csv, array( 'header' => true, 'replace' => false ) );
teq( 'nhập CSV: 1 dòng', 1, isset( $imp['inserted'] ) ? $imp['inserted'] : 0 );
$li = VHCP_Don::line_row( 'L_import1' );
teq( 'nhập CSV: đọc số có dấu chấm nghìn', 1500000.0, (float) $li['thanh_tien'] );
teq( 'nhập CSV: thực mua', 1450000.0, (float) $li['thuc_mua'] );
teq( 'nhập CSV: ngày kiểu Việt Nam', '2026-08-15', $li['ngay'] );
teq( 'nhập CSV: ô trống -> NULL (chưa nhập thuế)', null, $li['thue_suat'] );
teq( 'nhập CSV: nội dung có dấu phẩy trong ngoặc kép', 'Sơn tường, 2 lớp', $li['noi_dung'] );

$csv_cfg = "Cơ sở,Mã đơn vị,Phân loại lớn,Tên MISA\nVR SORA,VR_SORA,VR,VR Sora\n";
$imp2 = VHCP_Import::run( 'CH_CoSo', $csv_cfg, array( 'header' => true, 'replace' => true ) );
teq( 'nhập CSV cấu hình: 1 cơ sở', 1, $imp2['inserted'] );
teq( 'nhập CSV cấu hình: ghi đè bảng', 1, count( VHCP_Cfg::cfg_static()['coso'] ) );
teq( 'nhập CSV cấu hình: đúng mã đơn vị', 'VR_SORA', VHCP_Cfg::cfg_static()['coso'][0]['maDonVi'] );

// ---------------------------------------------------------------- 13b. trả lại đơn / không dùng / tách dòng
$d3  = VHCP_Don::create_don( 'T8/2026 (24/8-30/8/2026)', 'NV C' );
$m3  = $d3['maDon'];
$x1  = VHCP_Don::add_line( $m3, array( 'coso' => 'FARM PHAN THIẾT', 'ngay' => $today, 'phanLoaiTT' => 'Thanh toán cá nhân', 'nhom' => 'Chi phí cơ sở', 'noiDung' => 'Bơm nước', 'thanhTien' => 800000 ) );
VHCP_Don::gui_duyet_tam_ung( $m3 );
VHCP_Don::duyet_tam_ung( $m3, 'Trần Quản Lý', '' );
VHCP_Don::cap_tam_ung( $m3, 'Lê Kế Toán', 'Tiền mặt' );

// tách 1 dòng sang cơ sở khác
$dup = VHCP_Don::duplicate_line( $x1['id'], 'TÀU ESTELLA', 'Lê Kế Toán' );
t( 'tách dòng sang cơ sở khác', ! empty( $dup['success'] ) );
$g3 = VHCP_Don::get_don( $m3 );
teq( 'sau khi tách: 2 dòng', 2, count( $g3['lines'] ) );
teq( 'bản sao mang cơ sở mới', 'TÀU ESTELLA', $g3['lines'][1]['coso'] );

// bỏ tích "CN xử lý" -> dòng cá nhân chuyển sang luồng NCC
t( 'bỏ tích CN xử lý', ! empty( VHCP_Don::set_line_cn( $dup['id'], false )['success'] ) );
$g3 = VHCP_Don::get_don( $m3 );
teq( 'dòng bỏ tích tính về NCC', 800000, $g3['tongNCC']['thucChi'] );
teq( 'dòng bỏ tích không còn ở cá nhân', 800000, $g3['tongCN']['thucChi'] );

// trả lại đơn sau khi đã cấp -> quay về "Đã cấp tạm ứng" + mở khóa xóa hạng mục xin
VHCP_Don::gui_quyet_toan( $m3 );
$tra = VHCP_Don::tra_lai_don( $m3, 'Thiếu hóa đơn' );
teq( 'trả lại sau khi cấp -> Đã cấp tạm ứng', 'Đã cấp tạm ứng', $tra['target'] );
t( 'ghi chú mang cờ [Trả lại]', strpos( VHCP_Don::don_row( $m3 )['ghi_chu'], '[Trả lại]' ) !== false );
t( 'đơn bị trả lại: xóa được hạng mục xin', ! empty( VHCP_Don::delete_line( $dup['id'] )['success'] ) );
VHCP_Don::gui_quyet_toan( $m3 );
t( 'gửi lại thì gỡ cờ [Trả lại]', strpos( VHCP_Don::don_row( $m3 )['ghi_chu'], '[Trả lại]' ) === false, VHCP_Don::don_row( $m3 )['ghi_chu'] );

// "không dùng" tạm ứng
$d4 = VHCP_Don::create_don( 'T8/2026', 'NV D' );
$m4 = $d4['maDon'];
VHCP_Don::add_line( $m4, array( 'coso' => 'VR SORA', 'ngay' => $today, 'phanLoaiTT' => 'Thanh toán cá nhân', 'nhom' => 'Chi phí cơ sở', 'noiDung' => 'Dự phòng', 'thanhTien' => 500000 ) );
VHCP_Don::gui_duyet_tam_ung( $m4 );
VHCP_Don::duyet_tam_ung( $m4, 'Trần Quản Lý', '' );
t( 'chưa cấp tiền thì không đánh dấu Không dùng', empty( VHCP_Don::khong_dung_tam_ung( $m4 )['success'] ) );
VHCP_Don::cap_tam_ung( $m4, 'Lê Kế Toán', 'Tiền mặt' );
t( 'đánh dấu Không dùng', ! empty( VHCP_Don::khong_dung_tam_ung( $m4 )['success'] ) );
$r4 = VHCP_Don::don_row( $m4 );
teq( 'Không dùng -> chờ quyết toán', 'Chờ quyết toán', $r4['trang_thai'] );
t( 'Không dùng -> có cờ [Không dùng]', strpos( $r4['ghi_chu'], '[Không dùng]' ) !== false );

// tất toán tuần
t( 'đánh dấu tất toán tuần', ! empty( VHCP_Don::set_tat_toan_tuan( $m4, true, 'Trần Quản Lý' )['success'] ) );
$ds = VHCP_Don::list_dons();
$found4 = null;
foreach ( $ds as $x ) { if ( $x['maDon'] === $m4 ) { $found4 = $x; } }
t( 'danh sách hiện đã tất toán', $found4 && $found4['tatToan'] === true );
t( 'bỏ đánh dấu tất toán', ! empty( VHCP_Don::set_tat_toan_tuan( $m4, false, '' )['success'] ) );

VHCP_Don::delete_don_admin( $m3 );
VHCP_Don::delete_don_admin( $m4 );

// ---------------------------------------------------------------- 14. xóa đơn
$d2 = VHCP_Don::create_don( 'T8/2026', 'NV B' );
t( 'xóa đơn nháp', ! empty( VHCP_Don::delete_don( $d2['maDon'] )['success'] ) );
t( 'không xóa đơn đã xuất MISA bằng lệnh thường', empty( VHCP_Don::delete_don( $ma )['success'] ) );
t( 'Admin xóa vĩnh viễn được', ! empty( VHCP_Don::delete_don_admin( $ma )['success'] ) );
t( 'Admin xóa được cả đơn NCC', ! empty( VHCP_Don::delete_don_admin( $ma_ncc )['success'] ) );
teq( 'xóa đơn thì xóa luôn dòng chi', 0, count( VHCP_Don::cp_rows() ) );

// ---------------------------------------------------------------- 15. bảng hàm của REST API
require_once dirname( dirname( __DIR__ ) ) . '/wordpress/vhcp-chi-phi/includes/class-vhcp-api.php';
$map = VHCP_API::map();
$bad = array();
foreach ( $map as $name => $cb ) { if ( ! is_callable( $cb ) ) { $bad[] = $name; } }
teq( 'mọi hàm trong bảng REST đều tồn tại', array(), $bad );
t( 'bảng REST đủ nhiều hàm', count( $map ) >= 85, count( $map ) );

// Mọi hàm giao diện gọi (google.script.run.<tên>) phải có trong bảng.
$ui = file_get_contents( dirname( dirname( __DIR__ ) ) . '/wordpress/vhcp-chi-phi/templates/app.html' );
preg_match_all( '/(?:google\.script\.run|\brun)((?:\.with[A-Za-z]+Handler\([^;]*?\))*)\.([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $ui, $mm );
$called = array_unique( isset( $mm[2] ) ? $mm[2] : array() );
$missing = array();
foreach ( $called as $fn ) {
	if ( strpos( $fn, 'with' ) === 0 ) { continue; }   // withSuccessHandler/withFailureHandler là của lớp shim
	if ( ! isset( $map[ $fn ] ) ) { $missing[] = $fn; }
}
teq( 'giao diện không gọi hàm nào thiếu ở backend', array(), $missing );
t( 'giao diện có gọi backend', count( $called ) > 15, count( $called ) );

// Toàn bộ hàm PUBLIC của Code.gs (app Apps Script cũ) phải có mặt trong bảng REST.
$gas = array(
	'addBPLine',
	'addDuAnLine',
	'addLine',
	'addMkDonLine',
	'approveDuAn',
	'capTamUng',
	'capTamUngNhieu',
	'changePin',
	'closeBP',
	'closeDuAn',
	'closeMkDon',
	'confirmDuAnPay',
	'createBP',
	'createDon',
	'createDuAn',
	'createMkDon',
	'deleteBP',
	'deleteBPLine',
	'deleteDon',
	'deleteDonAdmin',
	'deleteDuAn',
	'deleteDuAnLine',
	'deleteLine',
	'deleteMkDon',
	'deleteMkDonLine',
	'duplicateLine',
	'duyetTamUng',
	'duyetTamUngNhieu',
	'editMkDon',
	'ensureCoSoChung',
	'exportMisa',
	'exportMisaBP',
	'exportMisaKyThuat',
	'exportMisaMarketing',
	'getBP',
	'getBootstrap',
	'getConfig',
	'getDon',
	'getDuAn',
	'getFinanceReport',
	'getGianReport',
	'getLog',
	'getMkDon',
	'getPendingModules',
	'getQuyen',
	'getQuyenConfig',
	'getSoDuDauKy',
	'getUsers',
	'getVanHanhTuan',
	'guiDuyetTamUng',
	'guiQuyetToan',
	'khongDungTamUng',
	'listBP',
	'listDons',
	'listDuAn',
	'listMkDon',
	'logAction',
	'login',
	'markExported',
	'migrateOldImages',
	'renameBP',
	'renameDuAn',
	'reopenBP',
	'reopenDuAn',
	'reopenMkDon',
	'returnDuAn',
	'saveConfig',
	'saveQuyetToan',
	'setDuPhong',
	'setLineAnh',
	'setLineCN',
	'setLineThucMua',
	'setQuyen',
	'setSoDuDauKy',
	'setTamUng',
	'setTatToanTuan',
	'setTuExtra',
	'submitDuAn',
	'traLaiDon',
	'traLaiDonNhieu',
	'unconfirmDuAnPay',
	'undoConfig',
	'updateBPLine',
	'updateDuAnLine',
	'updateLine',
	'updateMkDonLine',
	'uploadDuAnDoc',
	'uploadImage',
	'xacNhanQTCNNhieu',
	'xacNhanQuyetToanCN',
	'xacNhanQuyetToanNCC',
);
/**
 * HÀM CỦA APP CŨ ĐÃ CỐ Ý BỎ — ghi rõ ở đây, kèm lý do.
 *
 * Danh sách trên là để không ÂM THẦM đánh rơi hàm nào lúc dựng lại. Bỏ hàm thì phải là
 * một quyết định có tên, không phải xóa khỏi danh sách cho phép thử xanh trở lại.
 */
$da_bo = array(
	// Luồng thật: cấp tạm ứng xong thì NV bổ sung hóa đơn, chốt chi phí rồi gửi THẲNG cho
	// kế toán. Khâu "quản lý gom rồi đẩy" chỉ là chặng dừng không ai làm gì. Đơn còn kẹt ở
	// trạng thái cũ được VHCP_DB::bo_khau_gom() dời sang "Chờ quyết toán" lúc nâng cấp.
	'dayChoKeToan' => 'bỏ khâu quản lý gom hóa đơn (1.21.0)',
);
foreach ( array_keys( $da_bo ) as $fn ) {
	t( 'hàm đã bỏ thì phải bỏ HẲN, không còn nửa vời: ' . $fn, ! isset( $map[ $fn ] ), $fn );
	t( 'và không nằm trong danh sách phải có: ' . $fn, ! in_array( $fn, $gas, true ), $fn );
}
$chua_co = array();
foreach ( $gas as $fn ) { if ( ! isset( $map[ $fn ] ) ) { $chua_co[] = $fn; } }
teq( 'đủ 100% hàm của app Apps Script cũ (trừ phần đã cố ý bỏ)', array(), $chua_co );
// 92 hàm của app cũ, trừ 1 hàm đã cố ý bỏ (xem $da_bo ở trên) = 91 hàm còn phải giữ.
// Con số này chốt cứng để không ai lặng lẽ rút bớt một dòng khỏi danh sách trên.
teq( 'số hàm cũ còn phải giữ', 92 - count( $da_bo ), count( $gas ) );
teq( 'số hàm cũ đã cố ý bỏ', 1, count( $da_bo ) );

// ---------------------------------------------------------------- 16. cửa API: phiên & vai trò
function api( $fn, $args = array(), $token = '' ) {
	$res = VHCP_API::handle( new WP_REST_Request( array( 'fn' => $fn, 'args' => $args, 'token' => $token ) ) );
	return array( 'status' => $res->get_status(), 'body' => $res->get_data() );
}

$a = api( 'login', array( '4321' ) );
teq( 'API login: 200', 200, $a['status'] );
t( 'API login: trả token', ! empty( $a['body']['data']['token'] ) );
$tok_admin = $a['body']['data']['token'];

$a = api( 'getBootstrap' );
teq( 'API không token: 401', 401, $a['status'] );
teq( 'API không token: mã no_session', 'no_session', $a['body']['code'] );

$a = api( 'getBootstrap', array(), $tok_admin );
teq( 'API có token: 200', 200, $a['status'] );
t( 'API có token: trả dữ liệu', isset( $a['body']['data']['coso'] ) );

$a = api( 'getBootstrap', array(), str_repeat( 'a', 64 ) );
teq( 'API token bịa: 401', 401, $a['status'] );

$a = api( 'khongCoHamNay', array(), $tok_admin );
teq( 'API hàm lạ: 400', 400, $a['status'] );

$tok_nv = VHCP_Auth::issue_token( 'NV E', 'Nhân viên', 'VR SORA', '' );
$tok_ql = VHCP_Auth::issue_token( 'QL F', 'Quản lý', '', '' );

$a = api( 'getUsers', array(), $tok_nv );
teq( 'Nhân viên đọc danh sách PIN: 403', 403, $a['status'] );

// KẾ TOÁN vào được Cấu hình (khai mã tài khoản là việc của kế toán) — NHƯNG không sửa
// được tài khoản Admin. Chặn ở máy chủ, không chỉ khoá ô nhập trên giao diện.
$tok_kt = VHCP_Auth::issue_token( 'Kế Toán G', 'Kế toán cá nhân', '', '' );
teq( 'Kế toán mở được Cấu hình', 200, api( 'getUsers', array(), $tok_kt )['status'] );
teq( 'Kế toán lưu được Cấu hình', 200, api( 'saveConfig', array( array() ), $tok_kt )['status'] );
VHCP_Cfg::save_config( array( 'users' => array(
	array( 'ten' => 'Admin', 'pin' => '4321', 'vaiTro' => 'Admin' ),
	array( 'ten' => 'Kế Toán G', 'pin' => '2468', 'vaiTro' => 'Kế toán cá nhân' ),
) ) );
// Kế toán thử đổi PIN của Admin + tự phong mình làm Admin
api( 'saveConfig', array( array( 'users' => array(
	array( 'ten' => 'Admin', 'pin' => '9999', 'vaiTro' => 'Admin' ),
	array( 'ten' => 'Kế Toán G', 'pin' => '2468', 'vaiTro' => 'Kế toán cá nhân' ),
	array( 'ten' => 'Tự Phong', 'pin' => '7777', 'vaiTro' => 'Admin' ),
) ) ), $tok_kt );
$sau = VHCP_Cfg::get_users();
$ad = null; $tp = null;
foreach ( $sau as $u ) { if ( $u['ten'] === 'Admin' ) { $ad = $u; } if ( $u['ten'] === 'Tự Phong' ) { $tp = $u; } }
teq( 'kế toán KHÔNG đổi được PIN của Admin', '4321', $ad ? $ad['pin'] : null );
t( 'kế toán KHÔNG tự phong mình làm Admin', $tp === null );
t( 'PIN Admin cũ vẫn đăng nhập được', ! empty( VHCP_Auth::login( '4321' )['ok'] ) );
t( 'PIN kế toán đặt cho Admin thì không', empty( VHCP_Auth::login( '9999' )['ok'] ) );
// Admin thì sửa được
api( 'saveConfig', array( array( 'users' => array(
	array( 'ten' => 'Admin', 'pin' => '4321', 'vaiTro' => 'Admin' ),
	array( 'ten' => 'Kế Toán G', 'pin' => '2468', 'vaiTro' => 'Kế toán cá nhân' ),
	array( 'ten' => 'Người Admin Thêm', 'pin' => '5150', 'vaiTro' => 'Nhân viên' ),
) ) ), $tok_admin );
$co_moi = false;
foreach ( VHCP_Cfg::get_users() as $u ) { if ( $u['ten'] === 'Người Admin Thêm' ) { $co_moi = true; } }
t( 'Admin vẫn sửa được bảng người dùng', $co_moi );

teq( 'lý do: forbidden', 'forbidden', $a['body']['code'] );
teq( 'Quản lý đọc được danh sách người dùng', 200, api( 'getUsers', array(), $tok_ql )['status'] );
teq( 'Nhân viên lưu cấu hình: 403', 403, api( 'saveConfig', array( array() ), $tok_nv )['status'] );
teq( 'Nhân viên sửa ma trận quyền: 403', 403, api( 'setQuyen', array( array() ), $tok_nv )['status'] );
teq( 'Quản lý xóa vĩnh viễn đơn: 403 (chỉ Admin)', 403, api( 'deleteDonAdmin', array( 'D_x' ), $tok_ql )['status'] );
teq( 'Nhân viên vẫn nhập đơn bình thường', 200, api( 'createDon', array( 'T8/2026', 'NV E' ), $tok_nv )['status'] );

$a = api( 'vhcpLogout', array( $tok_nv ), $tok_nv );
teq( 'API đăng xuất: 200', 200, $a['status'] );
teq( 'token sau đăng xuất hết hiệu lực: 401', 401, api( 'getBootstrap', array(), $tok_nv )['status'] );

// ---------------------------------------------------------------- 17. SỔ CHI PHÍ + loại chi phí gắn mã tài khoản
$dm = VHCP_Cfg::cfg_static();
t( 'danh mục loại chi phí tự dựng từ nhóm mặt hàng', count( $dm['loaiChiPhi'] ) >= 13, count( $dm['loaiChiPhi'] ) );

// Khai mã tài khoản cho 3 loại chi phí
VHCP_Cfg::save_config( array( 'loaiChiPhi' => array(
	array( 'ten' => 'Chi phí cơ sở',   'tkNo' => '64127', 'tkCo' => '',    'maDt' => '',       'boPhan' => '' ),
	array( 'ten' => 'NVL đồ ăn - Mua lẻ', 'tkNo' => '6421', 'tkCo' => '',  'maDt' => '',       'boPhan' => '' ),
	array( 'ten' => 'Chi phí tháo dỡ', 'tkNo' => '2413',  'tkCo' => '331', 'maDt' => 'NCC_XX', 'boPhan' => 'Kỹ thuật' ),
) ) );
$tk = VHCP_Cfg::loai_tk( 'Chi phí cơ sở' );
teq( 'tra mã TK theo loại chi phí', '64127', $tk['tkNo'] );
teq( 'loại chưa khai -> mã rỗng', '', VHCP_Cfg::loai_tk( 'Không có loại này' )['tkNo'] );

// Nhập phẳng: chọn loại chi phí rồi nhập, không cần lập đơn
$c1 = VHCP_SoChi::add( array( 'ngay' => $today, 'coso' => 'VR SORA', 'loai' => 'Chi phí cơ sở', 'noiDung' => 'Thay bóng đèn', 'soLuong' => 4, 'donGia' => 150000, 'hinhThuc' => 'Tạm ứng NV' ), 'Nguyễn Văn A' );
t( 'thêm dòng sổ chi phí', ! empty( $c1['success'] ) );
teq( 'TK Nợ gắn theo loại chi phí', '64127', $c1['tkNo'] );
teq( 'TK Có mặc định = 141 (tạm ứng NV)', '141', $c1['tkCo'] );

$c2 = VHCP_SoChi::add( array( 'ngay' => $today, 'coso' => 'VR SORA', 'loai' => 'Chi phí tháo dỡ', 'noiDung' => 'Thuê xe cẩu', 'soTien' => 3000000, 'hinhThuc' => 'Trực tiếp NCC', 'thueSuat' => 8 ), 'KT' );
teq( 'TK Nợ loại kỹ thuật', '2413', $c2['tkNo'] );
teq( 'danh mục ghi đè TK Có', '331', $c2['tkCo'] );

$c3 = VHCP_SoChi::add( array( 'ngay' => $today, 'coso' => 'TÀU ESTELLA', 'loai' => 'NVL đồ ăn - Mua lẻ', 'noiDung' => 'Rau củ', 'soTien' => 500000, 'hinhThuc' => 'Tạm ứng NV' ), 'NV B' );
t( 'thêm dòng cơ sở khác', ! empty( $c3['success'] ) );
t( 'thiếu loại chi phí thì không cho nhập', empty( VHCP_SoChi::add( array( 'coso' => 'VR SORA', 'soTien' => 1000 ) )['success'] ) );

$L = VHCP_SoChi::list_chi();
teq( 'sổ chi phí: 3 dòng', 3, $L['soDong'] );
teq( 'sổ chi phí: tổng tiền', 4100000, $L['tong'] );
teq( 'số tiền tự tính SL × ĐG', 600000, $L['items'][2]['soTien'] );
teq( 'tiền thuế tính theo thuế suất', 240000, $L['items'][1]['tienThue'] );
teq( 'kỳ tự điền theo tháng của ngày chi', ( new DateTime( 'now', VHCP_Util::tz() ) )->format( 'm/Y' ), $L['items'][0]['ky'] );

// gom theo LOẠI CHI PHÍ và theo MÃ TÀI KHOẢN — đúng thứ anh cần để "gọi lại sau này"
$by_loai = array();
foreach ( $L['byLoai'] as $x ) { $by_loai[ $x['loai'] ] = $x; }
teq( 'gom theo loại: chi phí cơ sở', 600000, $by_loai['Chi phí cơ sở']['tien'] );
teq( 'gom theo loại: kèm mã TK', '64127', $by_loai['Chi phí cơ sở']['tkNo'] );
$by_tk = array();
foreach ( $L['byTkNo'] as $x ) { $by_tk[ $x['tkNo'] ] = $x['tien']; }
teq( 'gom theo mã TK 2413', 3000000, $by_tk['2413'] );
teq( 'gom theo mã TK 6421', 500000, $by_tk['6421'] );

teq( 'lọc theo cơ sở', 3600000, VHCP_SoChi::list_chi( array( 'coso' => 'VR SORA' ) )['tong'] );
teq( 'lọc theo mã tài khoản', 3000000, VHCP_SoChi::list_chi( array( 'tkNo' => '2413' ) )['tong'] );
teq( 'lọc theo loại chi phí', 600000, VHCP_SoChi::list_chi( array( 'loai' => 'Chi phí cơ sở' ) )['tong'] );
teq( 'tìm theo từ khóa', 1, VHCP_SoChi::list_chi( array( 'q' => 'cẩu' ) )['soDong'] );
teq( 'giới hạn cơ sở của nhân viên', 500000, VHCP_SoChi::list_chi( array( 'coso_scope' => array( 'TÀU ESTELLA' ) ) )['tong'] );

// sửa: đổi loại chi phí thì mã tài khoản đổi theo
$u = VHCP_SoChi::update( $c3['id'], array( 'ngay' => $today, 'coso' => 'TÀU ESTELLA', 'loai' => 'Chi phí cơ sở', 'noiDung' => 'Rau củ', 'soTien' => 500000, 'hinhThuc' => 'Tạm ứng NV' ) );
teq( 'đổi loại -> đổi mã TK Nợ', '64127', $u['tkNo'] );

// xuất MISA sổ chi phí: lấy thẳng mã trên dòng
$ex = VHCP_SoChi::export_misa( 'all', 'chuaxuat' );
teq( 'xuất MISA sổ chi phí: 3 dòng', 3, $ex['count'] );
teq( 'xuất MISA sổ chi phí: 10 cột', 10, count( $ex['cols'] ) );
$tkno_set = array();
foreach ( $ex['rows'] as $r ) { $tkno_set[ $r[5] ] = ( isset( $tkno_set[ $r[5] ] ) ? $tkno_set[ $r[5] ] : 0 ) + $r[7]; }
teq( 'xuất MISA: 2413 = 3.000.000', 3000000, $tkno_set['2413'] );
teq( 'xuất MISA: 64127 = 1.100.000', 1100000, $tkno_set['64127'] );
$row_kt = null;
foreach ( $ex['rows'] as $r ) { if ( $r[5] === '2413' ) { $row_kt = $r; } }
teq( 'xuất MISA: TK Có lấy từ dòng', '331', $row_kt[6] );
teq( 'xuất MISA: mã đối tượng lấy từ danh mục', 'NCC_XX', $row_kt[8] );
teq( 'xuất MISA: mã đơn vị theo cơ sở', 'VR_SORA', $row_kt[9] );

// chốt đã xuất -> khóa sửa/xóa
$mk = VHCP_SoChi::mark_exported( $ex['maDons'] );
teq( 'chốt đã xuất 3 dòng', 3, $mk['count'] );
teq( 'xuất lại lần 2 không còn dòng nào', 0, VHCP_SoChi::export_misa( 'all', 'chuaxuat' )['count'] );
teq( 'lọc đã xuất thì thấy lại', 3, VHCP_SoChi::export_misa( 'all', 'daxuat' )['count'] );
t( 'dòng đã xuất không sửa được', empty( VHCP_SoChi::update( $c1['id'], array( 'loai' => 'Chi phí cơ sở', 'soTien' => 1 ) )['success'] ) );
t( 'dòng đã xuất không xóa được', empty( VHCP_SoChi::delete( $c1['id'] )['success'] ) );
t( 'Admin bỏ chốt xuất', ! empty( VHCP_SoChi::unmark_exported( array( $c1['id'] ) )['success'] ) );
t( 'bỏ chốt rồi xóa được', ! empty( VHCP_SoChi::delete( $c1['id'] )['success'] ) );

// gán mã tài khoản cho dòng cũ (nhập trước khi khai danh mục)
$c4 = VHCP_SoChi::add( array( 'ngay' => $today, 'coso' => 'VR SORA', 'loai' => 'Nuôi thú', 'noiDung' => 'Cám', 'soTien' => 200000, 'hinhThuc' => 'Tạm ứng NV' ), 'NV C' );
teq( 'loại chưa khai mã -> dòng chưa có TK Nợ', '', $c4['tkNo'] );
$g = VHCP_SoChi::gan_ma_tai_khoan();
t( 'báo đúng loại còn thiếu mã', in_array( 'Nuôi thú', $g['thieuMa'], true ), $g['thieuMa'] );
$dm_now = VHCP_Cfg::cfg_static()['loaiChiPhi'];   // lấy danh mục HIỆN TẠI, không dùng bản chụp lúc đầu
VHCP_Cfg::save_config( array( 'loaiChiPhi' => array_merge( $dm_now, array( array( 'ten' => 'Nuôi thú', 'tkNo' => '6428' ) ) ) ) );
$g = VHCP_SoChi::gan_ma_tai_khoan();
teq( 'gán mã cho dòng cũ: 1 dòng', 1, $g['updated'] );
teq( 'dòng cũ đã có mã TK', '6428', VHCP_SoChi::list_chi( array( 'q' => 'cám' ) )['items'][0]['tkNo'] );

// nhập sổ chi phí từ CSV — mã tài khoản gắn ngay khi nạp
$csv_sc = "Ngày,Cơ sở,Loại chi phí,Nội dung,ĐVT,Số lượng,Đơn giá,Số tiền,Hình thức chi,Thuế suất,VAT,Đối tượng,Ghi chú,Ảnh\n"
        . "18/08/2026,VR SORA,Chi phí cơ sở,\"Bơm nước, thay ống\",lần,1,,\"2.400.000\",Trực tiếp NCC,8,Có VAT,CTY ABC,gấp,\n";
$imp_sc = VHCP_Import::run( 'SoChi', $csv_sc, array( 'header' => true, 'replace' => false ) );
teq( 'nhập CSV sổ chi phí: 1 dòng', 1, $imp_sc['inserted'] );
$sc_new = VHCP_SoChi::list_chi( array( 'q' => 'bơm nước' ) )['items'][0];
teq( 'CSV: số tiền có dấu chấm nghìn', 2400000, $sc_new['soTien'] );
teq( 'CSV: mã TK gắn từ danh mục', '64127', $sc_new['tkNo'] );
teq( 'CSV: TK Có theo hình thức trực tiếp', '331', $sc_new['tkCo'] );
teq( 'CSV: ngày kiểu Việt Nam', '18/08/2026', $sc_new['ngay'] );
teq( 'CSV: kỳ tự điền', '08/2026', $sc_new['ky'] );

// ---------------------------------------------------------------- 18. đơn vận hành cũng gắn mã tài khoản
VHCP_Cfg::save_config( array( 'coso' => array( array( 'ten' => 'VR SORA', 'maDonVi' => 'VR_SORA', 'phanLoaiLon' => 'VR', 'tenMisa' => 'VR Sora' ) ) ) );
$d9 = VHCP_Don::create_don( 'T8/2026', 'NV G' );
$m9 = $d9['maDon'];
$l9 = VHCP_Don::add_line( $m9, array( 'coso' => 'VR SORA', 'ngay' => $today, 'phanLoaiTT' => 'Thanh toán cá nhân', 'nhom' => 'Chi phí cơ sở', 'noiDung' => 'Sửa mái', 'soTien' => 0, 'thanhTien' => 900000 ) );
$g9 = VHCP_Don::get_don( $m9 );
teq( 'dòng đơn lưu sẵn TK Nợ theo loại chi phí', '64127', $g9['lines'][0]['tkNo'] );
teq( 'dòng đơn lưu sẵn TK Có theo phân loại', '141', $g9['lines'][0]['tkCo'] );

VHCP_Don::gui_duyet_tam_ung( $m9 );
VHCP_Don::duyet_tam_ung( $m9, 'Trần Quản Lý', '' );
VHCP_Don::cap_tam_ung( $m9, 'Lê Kế Toán', 'Tiền mặt' );
VHCP_Don::gui_quyet_toan( $m9 );
VHCP_Don::xac_nhan_quyet_toan_cn( $m9, 'Lê Kế Toán', 'Khớp', 0 );
$exd = VHCP_Misa::export_misa( 'all', 'chuaxuat', 'cn' );
teq( 'xuất MISA đơn: dùng mã TK Nợ đã gắn trên dòng', '64127', $exd['rows'][0][5] );
teq( 'xuất MISA đơn: không còn cảnh báo thiếu TK Nợ', array(), array_values( array_filter( $exd['warn'], function ( $w ) { return mb_strpos( $w, 'TK Nợ' ) !== false; } ) ) );

// ---------------------------------------------------------------- 19. vận hành tuần: đã bỏ gom Kỹ thuật/Công tác/Setup
$vh2 = VHCP_Report::van_hanh_tuan( $today );
t( 'vận hành tuần: không còn cột Kỹ thuật', ! array_key_exists( 'kt', $vh2['grand'] ), array_keys( $vh2['grand'] ) );
t( 'vận hành tuần: không còn cột Công tác', ! array_key_exists( 'ct', $vh2['grand'] ) );
t( 'vận hành tuần: không còn cột Setup', ! array_key_exists( 'setup', $vh2['grand'] ) );
t( 'vận hành tuần: có cột Sổ chi phí', array_key_exists( 'chi', $vh2['grand'] ) );
teq( 'vận hành tuần: tổng = vận hành + sổ chi phí + marketing', $vh2['grand']['vh'] + $vh2['grand']['chi'] + $vh2['grand']['mkt'], $vh2['grand']['tong'] );
$mods = array();
foreach ( $vh2['list'] as $m ) { foreach ( $m['lines'] as $l ) { $mods[ $l['mod'] ] = 1; } }
teq( 'chi tiết tuần chỉ còn 3 mảng', array(), array_values( array_diff( array_keys( $mods ), array( 'vh', 'chi', 'mkt' ) ) ) );

$gr2 = VHCP_Report::gian_report( 'VR SORA' );
$mods2 = array();
foreach ( $gr2['sections'] as $x ) { $mods2[] = $x['module']; }
t( 'báo cáo gian có mục Sổ chi phí', in_array( '💵 Sổ chi phí', $mods2, true ), $mods2 );

// ---------------------------------------------------------------- 20. Kỹ thuật / Marketing / Công tác cũng mang mã tài khoản
$dm_now2 = VHCP_Cfg::cfg_static()['loaiChiPhi'];
VHCP_Cfg::save_config( array( 'loaiChiPhi' => array_merge( $dm_now2, array(
	array( 'ten' => 'Chi phí công tác', 'tkNo' => '6427', 'tkCo' => '', 'maDt' => '', 'boPhan' => '' ),
	array( 'ten' => 'MKT - Hoạt náo',   'tkNo' => '6417', 'tkCo' => '', 'maDt' => '', 'boPhan' => '' ),
) ) ) );

// --- Kỹ thuật: dòng có loại chi phí -> mã theo danh mục; dòng không có -> giữ mã cũ 141/64125
$da2 = VHCP_DuAn::create_du_an( 'Tháo dỡ', 'TÀU BÌNH TÂN', 'KT' );
$md2 = $da2['maDA'];
VHCP_DuAn::add_line( $md2, array( 'noiDung' => 'Tháo vách', 'duToan' => 5000000, 'hinhThuc' => 'Tạm ứng', 'loaiCp' => 'Chi phí tháo dỡ' ) );
VHCP_DuAn::add_line( $md2, array( 'noiDung' => 'Nhân công', 'thucTe' => 4000000, 'capCha' => 'Tháo vách', 'loaiCp' => 'Chi phí tháo dỡ' ) );
VHCP_DuAn::add_line( $md2, array( 'noiDung' => 'Chưa gắn loại', 'thucTe' => 1000000, 'capCha' => 'Tháo vách' ) );
$gd2 = VHCP_DuAn::get_du_an( $md2 );
teq( 'dòng kỹ thuật lưu loại chi phí', 'Chi phí tháo dỡ', $gd2['lines'][1]['loaiCp'] );
teq( 'dòng kỹ thuật lưu TK Nợ theo danh mục', '2413', $gd2['lines'][1]['tkNo'] );
teq( 'danh mục ghi đè TK Có (331)', '331', $gd2['lines'][1]['tkCo'] );
teq( 'dòng chưa gắn loại thì chưa có mã', '', $gd2['lines'][2]['tkNo'] );

VHCP_DuAn::submit( $md2 );
VHCP_DuAn::approve( $md2, 'Lê Kế Toán' );
$exk2 = VHCP_Misa::export_ky_thuat();
$rows_bt = array();
foreach ( $exk2['rows'] as $r ) { if ( mb_strpos( $r[3], 'TÀU BÌNH TÂN' ) !== false ) { $rows_bt[] = $r; } }
teq( 'xuất MISA kỹ thuật: 2 dòng của dự án mới', 2, count( $rows_bt ) );
$tk_bt = array();
foreach ( $rows_bt as $r ) { $tk_bt[ $r[5] . '/' . $r[6] ] = $r[7]; }
teq( 'dòng có loại: Nợ 2413 / Có 331', 4000000, isset( $tk_bt['2413/331'] ) ? $tk_bt['2413/331'] : 0 );
teq( 'dòng chưa gắn loại: giữ cách cũ Nợ 141 / Có 64125', 1000000, isset( $tk_bt['141/64125'] ) ? $tk_bt['141/64125'] : 0 );

// --- Marketing
$mk2 = VHCP_MK::create_don( 'VR SORA', 'Sự kiện hè', '08/2026', 'Hoạt náo', 'MKT' );
VHCP_MK::add_line( $mk2['ma'], array( 'kenh' => 'Hoạt náo', 'noiDung' => 'Thuê nhóm nhảy', 'thucTe' => 2000000, 'hinhThuc' => 'Trực tiếp', 'ngay' => $today, 'loaiCp' => 'MKT - Hoạt náo' ) );
$gm2 = VHCP_MK::get_don( $mk2['ma'] );
teq( 'dòng marketing lưu mã TK', '6417', $gm2['lines'][0]['tkNo'] );
$exm2 = VHCP_Misa::export_marketing();
$found_mk = null;
foreach ( $exm2['rows'] as $r ) { if ( mb_strpos( $r[4], 'Thuê nhóm nhảy' ) !== false ) { $found_mk = $r; } }
teq( 'xuất MISA marketing: TK Nợ theo loại chi phí', '6417', $found_mk[5] );
teq( 'xuất MISA marketing: TK Có 331 (trực tiếp)', '331', $found_mk[6] );

// --- Công tác
$bp2 = VHCP_BP::create( 'Công tác', 'Đi Bình Dương', 'NV H', 'VR SORA', '08/2026', 'Admin' );
VHCP_BP::add_line( $bp2['ma'], array( 'noiDung' => 'Vé máy bay', 'duToan' => 2000000, 'thucTe' => 2200000, 'hinhThuc' => 'Tạm ứng', 'ngay' => $today, 'loaiCp' => 'Chi phí công tác' ) );
$gb2 = VHCP_BP::get( $bp2['ma'] );
teq( 'dòng công tác lưu mã TK', '6427', $gb2['lines'][0]['tkNo'] );
teq( 'TK Có 141 (tạm ứng)', '141', $gb2['lines'][0]['tkCo'] );
$exb2 = VHCP_Misa::export_bp( 'Công tác' );
$found_bp = null;
foreach ( $exb2['rows'] as $r ) { if ( mb_strpos( $r[4], 'Vé máy bay' ) !== false ) { $found_bp = $r; } }
teq( 'xuất MISA công tác: Nợ 6427', '6427', $found_bp[5] );
teq( 'xuất MISA công tác: Có 141', '141', $found_bp[6] );

// ---------------------------------------------------------------- 21. TRA THEO MÃ TÀI KHOẢN
$tra = VHCP_TraMa::search( array() );
t( 'tra theo mã: có dữ liệu mọi mảng', $tra['soDong'] > 5, $tra['soDong'] );
$mangs = array();
foreach ( $tra['byMang'] as $x ) { $mangs[ $x['mang'] ] = $x['tien']; }
t( 'gom được cả 5 nguồn', count( $mangs ) >= 4, array_keys( $mangs ) );
t( 'danh sách mã để chọn có 2413', in_array( '2413', $tra['maList'], true ), $tra['maList'] );

// Một mã ra nhiều mảng cùng lúc — đúng thứ cần: 2413 có ở cả Kỹ thuật lẫn Sổ chi phí
$t2413 = VHCP_TraMa::search( array( 'tkNo' => '2413' ) );
$m2413 = array();
foreach ( $t2413['byMang'] as $x ) { $m2413[ $x['mang'] ] = $x['tien']; }
teq( 'tra mã 2413: tổng gộp mọi mảng', 7000000, $t2413['tong'] );
teq( 'tra mã 2413: phần kỹ thuật', 4000000, isset( $m2413['kt'] ) ? $m2413['kt'] : 0 );
teq( 'tra mã 2413: phần sổ chi phí', 3000000, isset( $m2413['sochi'] ) ? $m2413['sochi'] : 0 );

$t6427 = VHCP_TraMa::search( array( 'tkNo' => '6427' ) );
teq( 'tra mã 6427: ra dòng công tác', 2200000, $t6427['tong'] );
teq( 'tra mã 6427: đúng nội dung', 'Vé máy bay', $t6427['items'][0]['noiDung'] );
teq( 'tra mã 6427: kèm mảng', 'ct', $t6427['items'][0]['mang'] );

$t64127 = VHCP_TraMa::search( array( 'tkNo' => '64127' ) );
$m64127 = array();
foreach ( $t64127['byMang'] as $x ) { $m64127[ $x['mang'] ] = 1; }
t( 'mã 64127 ra cả sổ chi phí và đơn vận hành', isset( $m64127['sochi'] ) && isset( $m64127['don'] ), array_keys( $m64127 ) );

// dòng cũ vẫn hiện, mang mã cũ, và được đếm riêng để biết còn phải khai
$macu = array();
foreach ( $tra['maCu'] as $x ) { $macu[ $x['mang'] ] = $x['n']; }
t( 'đếm được dòng còn dùng mã cũ 141/64125', array_sum( $macu ) > 0, $macu );
$t141 = VHCP_TraMa::search( array( 'tkNo' => '141' ) );
t( 'tra mã 141 ra các dòng chưa gắn loại', $t141['soDong'] > 0, $t141['soDong'] );

// lọc theo mảng / kỳ / cơ sở / từ khóa
teq( 'lọc theo mảng', 'ct', VHCP_TraMa::search( array( 'mang' => 'ct' ) )['items'][0]['mang'] );
teq( 'lọc theo cơ sở', 1, count( VHCP_TraMa::search( array( 'coso' => 'VR SORA', 'mang' => 'ct' ) )['byCoso'] ) );
teq( 'tìm theo từ khóa', 1, VHCP_TraMa::search( array( 'q' => 'nhóm nhảy' ) )['soDong'] );
$tk_ky = ( new DateTime( 'now', VHCP_Util::tz() ) )->format( 'm/Y' );
t( 'lọc theo kỳ', VHCP_TraMa::search( array( 'ky' => $tk_ky ) )['soDong'] > 0 );
teq( 'giới hạn cơ sở của nhân viên', 0, count( VHCP_TraMa::search( array( 'coso_scope' => array( 'Cơ sở không tồn tại' ) ) )['items'] ) );

// gán mã 1 lần cho mọi mảng: dòng kỹ thuật chưa gắn loại -> suy từ loại dự án
$gm_all = VHCP_TraMa::gan_ma_tat_ca();
t( 'gán mã tất cả mảng: có cập nhật', $gm_all['updated'] > 0, $gm_all );
$gd2b = VHCP_DuAn::get_du_an( $md2 );
teq( 'dòng kỹ thuật cũ được suy loại từ loại dự án', 'Chi phí tháo dỡ', $gd2b['lines'][2]['loaiCp'] );
teq( 'và có mã tài khoản', '2413', $gd2b['lines'][2]['tkNo'] );
$t2413b = VHCP_TraMa::search( array( 'tkNo' => '2413' ) );
t( 'sau khi gán: mã 2413 gom thêm các dòng kỹ thuật cũ', $t2413b['tong'] > $t2413['tong'], $t2413b['tong'] );
$m2413b = array();
foreach ( $t2413b['byMang'] as $x ) { $m2413b[ $x['mang'] ] = $x['tien']; }
teq( 'sau khi gán: kỹ thuật gồm cả dự án cũ', 16500000, isset( $m2413b['kt'] ) ? $m2413b['kt'] : 0 );
$exk3 = VHCP_Misa::export_ky_thuat();
$still_141 = 0;
foreach ( $exk3['rows'] as $r ) { if ( $r[5] === '141' && mb_strpos( $r[3], 'TÀU BÌNH TÂN' ) !== false ) { $still_141++; } }
teq( 'xuất MISA kỹ thuật: không còn dòng mã cũ ở dự án đã gán', 0, $still_141 );

// ---------------------------------------------------------------- KHAI 1 LOẠI CHO NHIỀU MẢNG
// "Chi phí cơ sở" thì mảng nào cũng dùng. Bắt khai lại 7 lần, mỗi lần phải mở một đơn ở
// cơ sở thuộc mảng đó, là không làm nổi — nên popup khai nhanh cho tích nhiều mảng.
$coso_goc_nm = VHCP_Cfg::cfg_static()['coso'];   // trả lại y nguyên ở cuối khối này
VHCP_Cfg::save_config( array( 'coso' => array(
	array( 'ten' => 'FARM PHAN THIẾT', 'maDonVi' => 'FARM_PT', 'phanLoaiLon' => 'FARM MN', 'tenMisa' => 'Farm PT' ),
	array( 'ten' => 'TÀU ESTELLA',     'maDonVi' => 'TAU_ES',  'phanLoaiLon' => 'TUTU MN', 'tenMisa' => 'Tàu Estella' ),
	array( 'ten' => 'VR SORA',         'maDonVi' => 'VR_S',    'phanLoaiLon' => 'EVENT VR MN', 'tenMisa' => 'VR Sora' ),
) ) );
$r_nm = VHCP_Cfg::khai_cho_coso( array(
	'ten' => 'Chi phí điện nước', 'tkNo' => '64127',
	'mangs' => array( 'FARM MN', 'TUTU MN', 'EVENT VR MN' ),
	'boPhan' => 'Cơ sở',
) );
t( 'khai 1 loại cho 3 mảng cùng lúc', ! empty( $r_nm['success'] ), $r_nm );
teq( 'mảng FARM MN nhận mã',     '64127', VHCP_Cfg::tkno_mx( 'Chi phí điện nước', 'FARM PHAN THIẾT' ) );
teq( 'mảng TUTU MN nhận mã',     '64127', VHCP_Cfg::tkno_mx( 'Chi phí điện nước', 'TÀU ESTELLA' ) );
teq( 'mảng EVENT VR MN nhận mã', '64127', VHCP_Cfg::tkno_mx( 'Chi phí điện nước', 'VR SORA' ) );
// Khai theo MẢNG chứ không theo từng cơ sở -> cơ sở mở SAU cũng dùng được ngay, khỏi khai lại.
$cs_moi = VHCP_Cfg::cfg_static()['coso'];
$cs_moi[] = array( 'ten' => 'FARM NHA TRANG', 'maDonVi' => 'FARM_NT', 'phanLoaiLon' => 'FARM MN', 'tenMisa' => 'Farm NT' );
VHCP_Cfg::save_config( array( 'coso' => $cs_moi ) );
teq( 'cơ sở mở SAU, cùng mảng -> dùng được luôn', '64127', VHCP_Cfg::tkno_mx( 'Chi phí điện nước', 'FARM NHA TRANG' ) );
// Mảng KHÔNG tích thì không được ăn theo — tích 3 mảng nghĩa là đúng 3 mảng đó.
VHCP_Cfg::save_config( array( 'coso' => array_merge( VHCP_Cfg::cfg_static()['coso'], array(
	array( 'ten' => 'FZ VŨNG TÀU', 'maDonVi' => 'FZ_VT', 'phanLoaiLon' => 'FZ MN', 'tenMisa' => 'FZ VT' ),
) ) ) );
teq( 'mảng KHÔNG tích thì không có mã', '', VHCP_Cfg::tkno_mx( 'Chi phí điện nước', 'FZ VŨNG TÀU' ) );
// Khai riêng 1 cơ sở vẫn phải chạy (dòng đặc thù)
$r_r = VHCP_Cfg::khai_cho_coso( array( 'ten' => 'Chi phí lặn vệ sinh', 'tkNo' => '64129', 'coso' => 'TÀU ESTELLA', 'rieng' => true ) );
t( 'khai riêng cho 1 cơ sở vẫn chạy', ! empty( $r_r['success'] ), $r_r );
teq( 'cơ sở đó có mã',        '64129', VHCP_Cfg::tkno_mx( 'Chi phí lặn vệ sinh', 'TÀU ESTELLA' ) );
teq( 'cơ sở khác cùng mảng KHÔNG ăn theo', '', VHCP_Cfg::tkno_mx( 'Chi phí lặn vệ sinh', 'FARM PHAN THIẾT' ) );
t( 'khai mà không tích mảng lẫn cơ sở -> bị chặn',
	empty( VHCP_Cfg::khai_cho_coso( array( 'ten' => 'X', 'tkNo' => '6428' ) )['success'] ) );

// ---- MỘT LOẠI CHI PHÍ · NHIỀU TK NỢ ----
// Kiểu 1: KHÁC MẢNG THÌ KHÁC MÃ (bảng ma trận Loại × Mảng đang chạy thế này).
VHCP_Cfg::khai_cho_coso( array( 'ten' => 'Chi phí cơ sở A', 'tkNo' => '64166', 'mangs' => array( 'FARM MN' ) ) );
VHCP_Cfg::khai_cho_coso( array( 'ten' => 'Chi phí cơ sở A', 'tkNo' => '64106', 'mangs' => array( 'TUTU MN' ) ) );
teq( 'cùng tên · mảng FARM ra mã FARM', '64166', VHCP_Cfg::tkno_mx( 'Chi phí cơ sở A', 'FARM PHAN THIẾT' ) );
teq( 'cùng tên · mảng TUTU ra mã TUTU', '64106', VHCP_Cfg::tkno_mx( 'Chi phí cơ sở A', 'TÀU ESTELLA' ) );
t( 'khai mảng sau KHÔNG xoá mã của mảng trước', VHCP_Cfg::tkno_mx( 'Chi phí cơ sở A', 'FARM PHAN THIẾT' ) === '64166' );

// Kiểu 2: CÙNG MỘT Ô (loại × mảng) mà NHIỀU MÃ -> app không đoán, người nhập chỉ rõ.
VHCP_Cfg::khai_cho_coso( array( 'ten' => 'Chi phí cơ sở A', 'tkNo' => '64168', 'mangs' => array( 'FARM MN' ), 'them' => 1 ) );
$ds_2 = VHCP_Cfg::tkno_mx_list( 'Chi phí cơ sở A', 'FARM PHAN THIẾT' );
teq( 'ô FARM giờ có 2 mã', 2, count( $ds_2 ) );
t( 'giữ cả mã cũ lẫn mã mới', in_array( '64166', $ds_2, true ) && in_array( '64168', $ds_2, true ), $ds_2 );
teq( 'ô 2 mã -> KHÔNG tự đoán, trả rỗng để người nhập chỉ rõ', '', VHCP_Cfg::tkno_mx( 'Chi phí cơ sở A', 'FARM PHAN THIẾT' ) );
teq( 'mảng khác không bị dính mã vừa thêm', '64106', VHCP_Cfg::tkno_mx( 'Chi phí cơ sở A', 'TÀU ESTELLA' ) );
t( 'mã người nhập chọn trong 2 mã đó là hợp lệ',
	VHCP_Cfg::ma_con_hop_le( 'Chi phí cơ sở A', 'FARM PHAN THIẾT', '64168' ) === '64168'
	&& VHCP_Cfg::ma_con_hop_le( 'Chi phí cơ sở A', 'FARM PHAN THIẾT', '64166' ) === '64166' );
t( 'mã ngoài danh sách thì KHÔNG hợp lệ', VHCP_Cfg::ma_con_hop_le( 'Chi phí cơ sở A', 'FARM PHAN THIẾT', '9999' ) === '' );
// Lúc xuất MISA: mã người nhập đã chọn phải được GIỮ, vì ô khai 2 mã thì máy không chọn hộ được
teq( 'xuất MISA giữ đúng mã người nhập đã chọn', '64168',
	VHCP_Cfg::tkno_xuat( 'Chi phí cơ sở A', 'FARM PHAN THIẾT', '64168' ) );
teq( 'và giữ mã kia nếu người nhập chọn mã kia', '64166',
	VHCP_Cfg::tkno_xuat( 'Chi phí cơ sở A', 'FARM PHAN THIẾT', '64166' ) );

// KHÔNG tích "thêm mã" thì mã mới THAY mã cũ — đây là cách sửa mã khai nhầm.
VHCP_Cfg::khai_cho_coso( array( 'ten' => 'Chi phí cơ sở A', 'tkNo' => '64167', 'mangs' => array( 'FARM MN' ) ) );
teq( 'không tích "thêm" -> ô chỉ còn đúng 1 mã mới', array( '64167' ), VHCP_Cfg::tkno_mx_list( 'Chi phí cơ sở A', 'FARM PHAN THIẾT' ) );
t( 'khai lại cùng mã 2 lần không sinh mã trùng',
	count( VHCP_Cfg::tkno_mx_list( 'Chi phí cơ sở A', 'FARM PHAN THIẾT' ) ) === 1
	&& ! empty( VHCP_Cfg::khai_cho_coso( array( 'ten' => 'Chi phí cơ sở A', 'tkNo' => '64167', 'mangs' => array( 'FARM MN' ), 'them' => 1 ) )['success'] )
	&& count( VHCP_Cfg::tkno_mx_list( 'Chi phí cơ sở A', 'FARM PHAN THIẾT' ) ) === 1 );

VHCP_Cfg::save_config( array( 'coso' => $coso_goc_nm ) );   // trả danh mục cơ sở cho các phép thử sau

// ------------------------------------------------- SỐ SÊ-RI BẢNG TÍNH LÀM HỎNG NGÀY
// GỐC CỦA 580 DÒNG "22/08/4621": ô ngày trong bảng tính xuất ra SỐ SÊ-RI ("46213.0" = số
// ngày kể từ 30/12/1899). parse_date cũ rơi xuống strtotime(), nó đọc "4621" thành NĂM
// rồi lấy ngày/tháng của hôm nhập -> ngày thật mất sạch.
teq( 'sê-ri bảng tính đọc ra ngày thật', '2026-07-10', VHCP_Util::parse_date( '46213.0' ) );
teq( 'sê-ri không có phần thập phân cũng đọc được', '2026-07-29', VHCP_Util::parse_date( '46232' ) );
teq( 'sê-ri của năm ngoái', '2025-08-01', VHCP_Util::parse_date( '45870.0' ) );
teq( 'ngày viết thường vẫn đọc như cũ', '2026-08-22', VHCP_Util::parse_date( '22/08/2026' ) );
teq( 'và dạng ISO vẫn đọc như cũ', '2026-08-22', VHCP_Util::parse_date( '2026-08-22' ) );
// Đừng nuốt nhầm: số 4 chữ số là NĂM, số nhỏ không phải ngày.
teq( 'số 4 chữ số không bị hiểu thành sê-ri', null, VHCP_Util::parse_date( '2026' ) );
teq( 'số quá nhỏ không phải sê-ri', null, VHCP_Util::parse_date( '12345' ) );
// strtotime là chỗ dễ bịa nhất -> chặn năm ngoài 2000–2100
teq( 'strtotime không được bịa ra năm ngoài 2000–2100', null, VHCP_Util::parse_date( '4621-08-23' ) );

// --- KHÔI PHỤC ngày thật cho dòng ĐÃ hỏng ---
// Năm 4621 nghĩa là sê-ri thuộc [46210…46219]; giao với KỲ của đơn thì còn đúng 1 ngày.
// Chữ số cuối của sê-ri MẤT HẲN: năm 4621 -> 10 ngày ứng viên (07/07…16/07/2026). Một
// TUẦN vẫn trùm 6 ngày trong số đó, nên đây chỉ thu hẹp về đúng tuần, KHÔNG khôi phục
// chính xác. Phép thử ghim đúng sự thật đó, không giả vờ là chính xác.
list( $ng_kp, $n_uv, $ds_uv ) = VHCP_Don::ngay_tu_nam_hong( 4621, 'T7/2026 (6/7-12/7/2026)' );
teq( 'một tuần vẫn còn 6 ứng viên', 6, $n_uv );
teq( 'nhiều ứng viên -> KHÔNG chọn bừa', '', $ng_kp );
t( 'mọi ứng viên đều nằm trong kỳ', $ds_uv[0] >= '2026-07-06' && end( $ds_uv ) <= '2026-07-12', $ds_uv );
// Kỳ nằm LỌT trong khoảng sê-ri thì mới còn đúng 1 ngày — lúc đó mới là ngày thật.
list( $ng1, $uv1 ) = VHCP_Don::ngay_tu_nam_hong( 4621, 'T7/2026 (16/7-16/7/2026)' );
teq( 'kỳ thu hẹp còn 1 ngày -> khôi phục chính xác', '2026-07-16', $ng1 );
teq( 'và đúng 1 ứng viên', 1, $uv1 );
list( $ng2, $uv2 ) = VHCP_Don::ngay_tu_nam_hong( 4621, 'T9/2026 (7/9-13/9/2026)' );
teq( 'kỳ không khớp sê-ri -> KHÔNG đoán bừa', '', $ng2 );
teq( 'và nói rõ là 0 ứng viên', 0, $uv2 );
teq( 'năm bình thường thì không phải chuyện của hàm này', '', VHCP_Don::ngay_tu_nam_hong( 2026, 'T7/2026 (6/7-12/7/2026)' )[0] );
teq( 'khoảng kỳ đọc đúng', array( '2026-08-10', '2026-08-16' ), VHCP_Don::khoang_ky( 'T8/2026 (10/8-16/8/2026)' ) );

$m_sr = VHCP_Don::create_don( 'T7/2026 (6/7-12/7/2026)', 'NV A' )['maDon'];
$id_sr = VHCP_Don::add_line( $m_sr, array( 'coso' => 'FARM PHAN THIẾT', 'nhom' => 'Chi phí cơ sở',
	'noiDung' => 'khăn lau', 'soLuong' => 1, 'donGia' => 246000, 'phanLoaiTT' => 'Thanh toán cá nhân' ) )['id'];
$wpdb->update( VHCP_DB::t( 'chiphi' ), array( 'ngay' => '4621-08-23' ), array( 'id' => $id_sr ) );
$xem_sr = VHCP_Don::sua_ngay_hong( 'seri', 0, false, $m_sr );
teq( 'xem trước thì chưa đụng dữ liệu', '23/08/4621', VHCP_Util::fmt( VHCP_Don::line_row( $id_sr )['ngay'] ) );
teq( 'nhiều ứng viên -> lùi về đầu kỳ', '06/07/2026', $xem_sr['items'][0]['moi'] );
teq( 'và ĐÁNH DẤU là ước lượng, không trộn với dòng chắc chắn', 1, $xem_sr['items'][0]['uocLuong'] );
teq( 'đếm riêng số dòng ước lượng', 1, $xem_sr['uocLuong'] );
$sr = VHCP_Don::sua_ngay_hong( 'seri', 0, true, $m_sr );
teq( 'sửa kiểu sê-ri: 1 dòng', 1, $sr['daSua'] );
teq( 'ngày mới nằm đúng trong kỳ của đơn', '06/07/2026', VHCP_Util::fmt( VHCP_Don::line_row( $id_sr )['ngay'] ) );

// Kỳ một ngày -> khôi phục CHÍNH XÁC, và không bị đánh dấu ước lượng
$m_sr1 = VHCP_Don::create_don( 'T7/2026 (16/7-16/7/2026)', 'NV A' )['maDon'];
$id_sr1 = VHCP_Don::add_line( $m_sr1, array( 'coso' => 'FARM PHAN THIẾT', 'nhom' => 'Chi phí cơ sở',
	'noiDung' => 'nước rửa chén', 'soLuong' => 1, 'donGia' => 211000, 'phanLoaiTT' => 'Thanh toán cá nhân' ) )['id'];
$wpdb->update( VHCP_DB::t( 'chiphi' ), array( 'ngay' => '4621-08-23' ), array( 'id' => $id_sr1 ) );
$sr1 = VHCP_Don::sua_ngay_hong( 'seri', 0, true, $m_sr1 );
teq( 'kỳ 1 ngày -> ra đúng ngày thật', '16/07/2026', VHCP_Util::fmt( VHCP_Don::line_row( $id_sr1 )['ngay'] ) );
teq( 'và KHÔNG bị đánh dấu ước lượng', 0, $sr1['uocLuong'] );

// SÊ-RI CÓ GIỜ: phần thập phân là phần của ngày. Bản vá đầu chỉ nhận ".0" nên mọi cột
// GIỜ (nhật ký, tạo lúc, ngày duyệt) vẫn hỏng — ra "04/01/6294" trong Nhật ký.
teq( 'sê-ri kèm giờ đọc ra đúng ngày', '2026-07-29', VHCP_Util::parse_date( '46232.6543' ) );
teq( 'và giữ được cả GIỜ', array( '2026-07-29', '15:42:12' ), VHCP_Util::seri( '46232.6543' ) );
teq( 'sê-ri .75 = 18 giờ', array( '2026-07-10', '18:00:00' ), VHCP_Util::seri( '46213.75' ) );
teq( 'sê-ri không thập phân thì 0 giờ', array( '2026-07-29', '00:00:00' ), VHCP_Util::seri( '46232' ) );
teq( 'dấu phẩy thập phân (máy Việt) cũng đọc được', '2026-07-29', VHCP_Util::parse_date( '46232,6543' ) );
teq( 'số ngoài khoảng sê-ri thì không nhận', null, VHCP_Util::seri( '62943.5' ) );
teq( 'năm 4 chữ số không phải sê-ri', null, VHCP_Util::seri( '2026' ) );

// NGÀY CỦA CHÍNH ĐƠN (tạo / duyệt / cấp tiền / quyết toán) là cột DATETIME, nên số sê-ri
// bảng tính bị MySQL loại thẳng lúc nạp -> MẤT HẲN, không khôi phục được như cột kỳ.
// Chỉ còn cách để Admin điền lại — mà ngày quyết toán còn là ngày dự phòng khi xuất MISA.
$m_dn = VHCP_Don::create_don( 'T8/2026 (10/8-16/8/2026)', 'NV A' )['maDon'];
// Đổi vai trò là ĐỔI TRẠNG THÁI TOÀN CỤC. Không trả lại nguyên trạng thì mọi phép thử
// phía sau chạy dưới quyền Admin — chúng vẫn xanh, nhưng xanh vì được bỏ qua kiểm tra.
$vt_cu = VHCP_Auth::vai_tro(); $ng_cu = VHCP_Auth::nguoi();
VHCP_Auth::dat_vai_tro( 'Admin', 'Admin' );
$r_dn = VHCP_Don::set_don_ngay( $m_dn, 'ngayCap', '12/08/2026' );
t( 'Admin điền được ngày cấp tiền', ! empty( $r_dn['success'] ), $r_dn );
teq( 'ghi đúng ngày', '12/08/2026', VHCP_Util::fmt( VHCP_Don::don_row( $m_dn )['ngay_cap'] ) );
t( 'xoá cho về trống cũng được', ! empty( VHCP_Don::set_don_ngay( $m_dn, 'ngayCap', '' )['success'] ) );
teq( 'và đã về trống', '', VHCP_Util::fmt( VHCP_Don::don_row( $m_dn )['ngay_cap'] ) );
t( 'ngày vô lý bị chặn', empty( VHCP_Don::set_don_ngay( $m_dn, 'ngayCap', '12/08/4621' )['success'] ) );
t( 'cột lạ bị chặn', empty( VHCP_Don::set_don_ngay( $m_dn, 'ngayBaDao', '12/08/2026' )['success'] ) );
t( 'sê-ri bảng tính điền vào cũng đọc được', ! empty( VHCP_Don::set_don_ngay( $m_dn, 'ngayQT', '46232.0' )['success'] ) );
teq( 'và ra đúng ngày', '29/07/2026', VHCP_Util::fmt( VHCP_Don::don_row( $m_dn )['ngay_qt'] ) );
VHCP_Auth::dat_vai_tro( 'Nhân viên', 'NV A' );
t( 'người khác KHÔNG sửa được ngày của đơn', empty( VHCP_Don::set_don_ngay( $m_dn, 'ngayCap', '12/08/2026' )['success'] ) );
VHCP_Auth::dat_vai_tro( $vt_cu, $ng_cu );   // trả nguyên trạng cho các phép thử sau
t( 'đã trả lại đúng vai trò trước đó', VHCP_Auth::vai_tro() === $vt_cu && VHCP_Auth::nguoi() === $ng_cu,
	array( VHCP_Auth::vai_tro(), $vt_cu ) );

// NHẬT KÝ: thời điểm sai thì NÓI LÀ KHÔNG BIẾT, đừng hiện ngày bịa.
// Nhật ký cũ nạp vào có cột thời điểm là sê-ri; thời điểm thật KHÔNG khôi phục được. Hiện
// "04/01/6294" là nói sai — mà nhật ký chính là chỗ tra ai làm gì lúc nào.
VHCP_Log::log_action( array( 'actor' => 'Admin', 'role' => 'Admin', 'action' => 'Thử', 'target' => 'X', 'detail' => '' ) );
$t_log = VHCP_DB::t( 'log' );
$id_log = (string) $wpdb->get_var( "SELECT id FROM $t_log ORDER BY id DESC LIMIT 1" );
$wpdb->update( $t_log, array( 'tg' => '6294-01-04 00:00:00' ), array( 'id' => $id_log ) );
$lg = VHCP_Log::get_log( array( 'limit' => 50 ) );
$hit_lg = null;
foreach ( $lg['items'] as $x ) { if ( $x['hanhDong'] === 'Thử' ) { $hit_lg = $x; } }
t( 'đọc ra dòng nhật ký vừa bơm', $hit_lg !== null, $lg );
teq( 'thời điểm vô lý -> KHÔNG hiện ngày bịa', '', $hit_lg['tg'] );
teq( 'và đánh dấu là hỏng để giao diện nói rõ', 1, $hit_lg['tgHong'] );
// Dòng bình thường vẫn hiện đủ ngày giờ
$wpdb->update( $t_log, array( 'tg' => '2026-08-23 15:51:29' ), array( 'id' => $id_log ) );
$lg2 = VHCP_Log::get_log( array( 'limit' => 50 ) );
$hit2 = null;
foreach ( $lg2['items'] as $x ) { if ( $x['hanhDong'] === 'Thử' ) { $hit2 = $x; } }
teq( 'thời điểm thường vẫn hiện đủ', '23/08/2026 15:51:29', $hit2['tg'] );
teq( 'và không bị đánh dấu hỏng', 0, $hit2['tgHong'] );

// --- CỘT KỲ CŨNG BỊ GHI BẰNG SÊ-RI ("46204.0") ---
// Kỳ hỏng kéo theo: đơn không lọc được theo tháng/tuần ở MỌI màn (đây là lý do "tháng 7
// không thấy đơn nào"), và cũng không suy ra được ngày cho dòng chi của đơn đó.
teq( 'nhãn kỳ dựng đúng tuần thứ 2 → chủ nhật', 'T7/2026 (6/7-12/7/2026)', VHCP_Don::nhan_ky( '2026-07-10' ) );
teq( 'ngày chủ nhật vẫn thuộc tuần đó', 'T7/2026 (6/7-12/7/2026)', VHCP_Don::nhan_ky( '2026-07-12' ) );
teq( 'ngày thứ 2 mở tuần mới', 'T7/2026 (13/7-19/7/2026)', VHCP_Don::nhan_ky( '2026-07-13' ) );
$m_ky = VHCP_Don::create_don( '46204.0', 'NV A' )['maDon'];
$xem_ky = VHCP_Don::sua_ky_hong( false );
$hit_ky = null;
foreach ( $xem_ky['items'] as $x ) { if ( $x['maDon'] === $m_ky ) { $hit_ky = $x; } }
t( 'dò ra đơn có kỳ hỏng', $hit_ky !== null, $xem_ky );
teq( 'xem trước: sê-ri 46204 = 01/07/2026', '01/07/2026', $hit_ky['ngay'] );
teq( 'và kỳ mới là tuần chứa ngày đó', 'T7/2026 (29/6-5/7/2026)', $hit_ky['moi'] );
teq( 'xem trước thì chưa đụng dữ liệu', '46204.0', VHCP_Don::don_row( $m_ky )['ky'] );
$sk = VHCP_Don::sua_ky_hong( true );
t( 'sửa kỳ: có sửa', $sk['daSua'] >= 1, $sk );
teq( 'kỳ của đơn đã đúng', 'T7/2026 (29/6-5/7/2026)', VHCP_Don::don_row( $m_ky )['ky'] );
teq( 'chạy lại thì không còn kỳ hỏng nào', 0, VHCP_Don::sua_ky_hong( false )['tong'] );
// Đơn kỳ bình thường KHÔNG được đụng vào
$m_ky2 = VHCP_Don::create_don( 'T8/2026 (10/8-16/8/2026)', 'NV A' )['maDon'];
VHCP_Don::sua_ky_hong( true );
teq( 'kỳ bình thường giữ nguyên', 'T8/2026 (10/8-16/8/2026)', VHCP_Don::don_row( $m_ky2 )['ky'] );

// ---------------------------------------------------------------- SỬA NGÀY VÔ LÝ
// Bảng xuất MISA ra "22/08/4625" lẫn "22/08/2026" trong CÙNG một đơn — vài dòng lẻ, sửa
// tay từng dòng thì được, nhưng phải có cả đường sửa hàng loạt cho nhanh.
$m_ng = VHCP_Don::create_don( 'T9/2026', 'NV A' )['maDon'];
$dong_ng = array( 'coso' => 'FARM PHAN THIẾT', 'nhom' => 'Chi phí cơ sở', 'noiDung' => 'cb 3 bọt tuyết',
	'soLuong' => 1, 'donGia' => 140000, 'phanLoaiTT' => 'Thanh toán cá nhân' );
$id_ng = VHCP_Don::add_line( $m_ng, $dong_ng )['id'];
$wpdb->update( VHCP_DB::t( 'chiphi' ), array( 'ngay' => '4625-08-22' ), array( 'id' => $id_ng ) );

// --- sửa TẠI CHỖ một dòng ---
$r_ng = VHCP_Don::set_line_ngay( $id_ng, '22/08/2026' );
t( 'sửa ngày tại chỗ chạy được', ! empty( $r_ng['success'] ), $r_ng );
teq( 'ngày mới đúng', '22/08/2026', VHCP_Util::fmt( VHCP_Don::line_row( $id_ng )['ngay'] ) );
t( 'sửa sang ngày vô lý thì bị chặn', empty( VHCP_Don::set_line_ngay( $id_ng, '22/08/4625' )['success'] ) );
t( 'ngày không đọc được cũng bị chặn', empty( VHCP_Don::set_line_ngay( $id_ng, 'hôm qua' )['success'] ) );
teq( 'bị chặn thì ngày cũ giữ nguyên', '22/08/2026', VHCP_Util::fmt( VHCP_Don::line_row( $id_ng )['ngay'] ) );

// --- dò / sửa HÀNG LOẠT ---
$wpdb->update( VHCP_DB::t( 'chiphi' ), array( 'ngay' => '4625-08-22' ), array( 'id' => $id_ng ) );
$xem = VHCP_Don::sua_nam_vo_ly( 0, $m_ng );          // 0 = chỉ DÒ, không đụng dữ liệu
t( 'dò ra dòng có năm vô lý', $xem['tong'] >= 1, $xem );
teq( 'dò thì KHÔNG sửa gì', 0, $xem['daSua'] );
teq( 'và dữ liệu chưa đổi', '22/08/4625', VHCP_Util::fmt( VHCP_Don::line_row( $id_ng )['ngay'] ) );
t( 'xem trước có nói rõ đơn nào · dòng nào · ngày thô trong máy',
	isset( $xem['items'][0]['maDon'] ) && isset( $xem['items'][0]['noiDung'] ) && $xem['items'][0]['tho'] === '4625-08-22', $xem['items'][0] );

$sua = VHCP_Don::sua_nam_vo_ly( 2026, $m_ng );
teq( 'sửa hàng loạt: 1 dòng', 1, $sua['daSua'] );
teq( 'GIỮ nguyên ngày và tháng, chỉ thay năm', '22/08/2026', VHCP_Util::fmt( VHCP_Don::line_row( $id_ng )['ngay'] ) );
teq( 'chạy lại thì không còn gì để sửa', 0, VHCP_Don::sua_nam_vo_ly( 0, $m_ng )['tong'] );
t( 'năm ngoài 2000–2100 thì từ chối', empty( VHCP_Don::sua_nam_vo_ly( 1899, $m_ng )['success'] ) );
// Không được đụng tới dòng có ngày ĐÚNG — sửa hàng loạt mà quét lan là hỏng cả sổ.
$id_ok = VHCP_Don::add_line( $m_ng, array_merge( $dong_ng, array( 'noiDung' => 'khăn lau', 'ngay' => '20/08/2026' ) ) )['id'];
VHCP_Don::sua_nam_vo_ly( 2026, $m_ng );
teq( 'dòng ngày đúng KHÔNG bị đụng vào', '20/08/2026', VHCP_Util::fmt( VHCP_Don::line_row( $id_ok )['ngay'] ) );

// ---------------------------------------------------------------- SỐ LƯỢNG LÀ BẮT BUỘC
// Dòng có ĐƠN GIÁ mà bỏ trống SỐ LƯỢNG thì thành tiền ra 0: tạm ứng xin thiếu đúng bằng
// số đó, mà nhìn bảng không thấy sai chỗ nào — số 0 trông y như dòng chưa điền.
$m_sl = VHCP_Don::create_don( 'T9/2026', 'NV A' )['maDon'];
$dong_thieu = array( 'coso' => 'FARM PHAN THIẾT', 'nhom' => 'Chi phí cơ sở', 'noiDung' => 'Giấy in A4',
	'donGia' => 81000, 'phanLoaiTT' => 'Thanh toán cá nhân' );
$r_sl = VHCP_Don::add_line( $m_sl, $dong_thieu );
t( 'thêm hạng mục có đơn giá mà THIẾU số lượng -> bị chặn', empty( $r_sl['success'] ), $r_sl );
t( 'và báo rõ vì sao (nói cả con số đơn giá)',
	isset( $r_sl['error'] ) && strpos( $r_sl['error'], 'SỐ LƯỢNG' ) !== false && strpos( $r_sl['error'], '81.000' ) !== false, $r_sl );
teq( 'dòng hỏng KHÔNG được ghi xuống', 0, count( VHCP_Don::get_don( $m_sl, false )['lines'] ) );

$dong_thieu['soLuong'] = 0;
t( 'số lượng = 0 cũng bị chặn', empty( VHCP_Don::add_line( $m_sl, $dong_thieu )['success'] ) );
$dong_thieu['soLuong'] = 2;
$r_ok = VHCP_Don::add_line( $m_sl, $dong_thieu );
t( 'có số lượng thì thêm được', ! empty( $r_ok['success'] ), $r_ok );
$ls_sl = VHCP_Don::get_don( $m_sl, false )['lines'];
teq( 'và thành tiền tính đúng 2 × 81.000', 162000, (float) $ls_sl[0]['thanhTien'] );

// Gõ THẲNG thành tiền (không qua đơn giá) vẫn là cách nhập hợp lệ — không được chặn nhầm.
$r_tt = VHCP_Don::add_line( $m_sl, array( 'coso' => 'FARM PHAN THIẾT', 'nhom' => 'Chi phí cơ sở',
	'noiDung' => 'Khoán trọn gói', 'thanhTien' => 500000, 'phanLoaiTT' => 'Thanh toán cá nhân' ) );
t( 'gõ thẳng thành tiền, không đơn giá -> vẫn cho qua', ! empty( $r_tt['success'] ), $r_tt );
// Dòng trống hẳn thì để chỗ khác lo, đây không phải việc của phép kiểm số lượng
$r_trong = VHCP_Don::add_line( $m_sl, array( 'coso' => 'FARM PHAN THIẾT', 'nhom' => 'Chi phí cơ sở', 'noiDung' => 'Chưa điền' ) );
t( 'dòng chưa có tiền gì thì không bị phép kiểm này chặn', ! empty( $r_trong['success'] ), $r_trong );

// Sửa dòng cũng phải chặn — nếu không thì thêm đúng rồi sửa cho trống là lọt.
$id_sua = (string) $ls_sl[0]['id'];
$r_sua = VHCP_Don::update_line( $id_sua, array( 'coso' => 'FARM PHAN THIẾT', 'nhom' => 'Chi phí cơ sở',
	'noiDung' => 'Giấy in A4', 'donGia' => 81000, 'soLuong' => '', 'phanLoaiTT' => 'Thanh toán cá nhân' ) );
t( 'SỬA dòng thành thiếu số lượng cũng bị chặn', empty( $r_sua['success'] ), $r_sua );
teq( 'dòng cũ giữ nguyên số tiền sau khi sửa hỏng bị chặn', 162000, (float) VHCP_Don::line_row( $id_sua )['thanh_tien'] );

// ---------------------------------------------------------------- 141 KHÔNG ĐƯỢC LỌT VÀO CỘT TK NỢ
// Bơm mã tạm ứng vào MỌI dòng của MỌI mảng rồi xuất lại cả 5 đường. Đây chính là cảnh
// anh Thắng gặp: bảng xuất ra "Nợ 141 · Có 141". Quét ở cuối tệp vì lúc này cả 5 mảng
// đều đã có dữ liệu thật do các phần trên dựng ra.
global $wpdb;
foreach ( array( 'chiphi', 'so_chi', 'da_line', 'mk_line', 'bp_line' ) as $bg ) {
	$tb = VHCP_DB::t( $bg );
	$wpdb->query( "UPDATE $tb SET tk_no='141'" );
}
$cac_duong = array(
	'đơn vận hành' => VHCP_Misa::export_misa( 'all', 'chuaxuat', 'all' ),
	'sổ chi phí'   => VHCP_SoChi::export_misa( 'all', 'chuaxuat' ),
	'kỹ thuật'     => VHCP_Misa::export_ky_thuat(),
	'marketing'    => VHCP_Misa::export_marketing(),
	'công tác'     => VHCP_Misa::export_bp( 'all' ),
);
foreach ( $cac_duong as $ten_duong => $kq ) {
	$co_ben_tra = 0; $co_dong = count( $kq['rows'] );
	foreach ( $kq['rows'] as $rw ) {
		if ( VHCP_Cfg::la_tk_ben_tra( (string) $rw[5] ) ) { $co_ben_tra++; }
	}
	t( 'xuất "' . $ten_duong . '": có dòng để soi (phép thử không xanh nhờ bảng rỗng)', $co_dong > 0, $co_dong );
	teq( 'xuất "' . $ten_duong . '": KHÔNG mã bên trả tiền ở cột TK Nợ', 0, $co_ben_tra );
}

// Cảnh báo NGÀY VÔ LÝ: gom theo giá trị, in kèm giá trị thô, đủ cho cả 5 đường.
$tb_cp = VHCP_DB::t( 'chiphi' );
$wpdb->query( "UPDATE $tb_cp SET ngay='4622-08-22'" );
$w_ngay = VHCP_Misa::export_misa( 'all', 'chuaxuat', 'all' )['warn'];
$cau_ngay = '';
foreach ( $w_ngay as $w ) { if ( strpos( $w, '4622' ) !== false ) { $cau_ngay = $w; } }
t( 'cảnh báo ngày: có báo', $cau_ngay !== '', $w_ngay );
t( 'cảnh báo ngày: GOM lại 1 câu, không phải mỗi dòng một câu',
	count( array_filter( $w_ngay, function ( $w ) { return strpos( $w, 'Ngày vô lý' ) !== false; } ) ) === 1, $w_ngay );
t( 'cảnh báo ngày: có đếm số dòng', preg_match( '/— \d+ dòng/u', $cau_ngay ) === 1, $cau_ngay );
t( 'cảnh báo ngày: in kèm GIÁ TRỊ THÔ trong máy để truy nguồn',
	strpos( $cau_ngay, 'Giá trị đang lưu trong máy: "4622-08-22"' ) !== false, $cau_ngay );
t( 'cảnh báo ngày: kể tên đơn để mở ra sửa', strpos( $cau_ngay, 'đơn (' ) !== false, $cau_ngay );
$wpdb->query( "UPDATE $tb_cp SET ngay='" . VHCP_Util::today_sql() . "'" );   // trả ngày lại cho các phép thử sau


// ---------------------------------------------------------------- 22. NẠP DỮ LIỆU CŨ: mã tài khoản tự gán ngay khi nạp
// (a) dòng chi của đơn — mã lấy theo cột "Nhóm mặt hàng"
$csv_cp = "ID,Mã đơn,Cơ sở,Ngày,Phân loại TT,Đối tượng,Nhóm mặt hàng,Nội dung,ĐVT,Số lượng,Đơn giá,Thành tiền,Ghi chú,Ảnh,Tạo lúc,Thuế suất (%),Tiền thuế,Thực mua,CN xử lý,Phát sinh\n"
        . "L_sync1,{$m9},VR SORA,19/08/2026,Thanh toán cá nhân,NV,Chi phí cơ sở,Thay khóa cửa,lần,1,,\"450.000\",,,,,,,1,0\n";
teq( 'nạp CSV dòng chi: 1 dòng', 1, VHCP_Import::run( 'ChiPhi', $csv_cp, array( 'header' => true ) )['inserted'] );
$li2 = VHCP_Don::line_row( 'L_sync1' );
teq( 'dòng chi nạp từ CSV tự có TK Nợ', '64127', (string) $li2['tk_no'] );
teq( 'dòng chi nạp từ CSV tự có TK Có', '141', (string) $li2['tk_co'] );

// (b) tab dự án kỹ thuật — file KHÔNG có cột loại chi phí -> tự suy theo loại dự án (Tháo dỡ)
$csv_da = "Nội dung hạng mục,Chi phí dự toán,Chi phí thực tế,Số lượng,Đơn giá,Thành tiền,VAT,Ảnh,Bộ phận / Gian,Ghi chú,Thuộc hạng mục lớn,Hình thức chi,Hồ sơ\n"
        . "h1,h2,h3,h4,h5,h6,h7,h8,h9,h10,h11,h12,h13\n"
        . ",,,,,,,,,,,,\n"
        . "x,x,x,x,x,x,x,x,x,x,x,x,x\n"
        . "Vận chuyển phế liệu,,\"1.800.000\",,,,,,Gian B,,Tháo vách,Trực tiếp,\n";
teq( 'nạp CSV tab dự án: 1 dòng', 1, VHCP_Import::run( 'DA_Sheet', $csv_da, array( 'ma' => $md2 ) )['inserted'] );
$da_lines = VHCP_DuAn::get_du_an( $md2 )['lines'];
$da_new = null;
foreach ( $da_lines as $l ) { if ( $l['noiDung'] === 'Vận chuyển phế liệu' ) { $da_new = $l; } }
teq( 'dòng dự án nạp từ CSV: tự suy loại chi phí theo loại dự án', 'Chi phí tháo dỡ', $da_new['loaiCp'] );
teq( 'dòng dự án nạp từ CSV: có TK Nợ', '2413', $da_new['tkNo'] );

// (c) hạng mục marketing — có cột "Loại chi phí" thì dùng, không có thì để trống (giữ mã cũ)
$csv_mk = "Mã dòng,Mã đơn,Kênh,Nội dung,Ngân sách,Thực chi,Hình thức chi,VAT,Kết quả,Ngày,Ghi chú,Hồ sơ,Loại chi phí\n"
        . "MKL_sync1,{$mk2['ma']},Facebook Ads,Boost bài hội chợ,,\"1.200.000\",Trực tiếp,Có VAT,50,19/08/2026,,,MKT - Hoạt náo\n"
        . "MKL_sync2,{$mk2['ma']},Zalo,Tin nhắn ZNS,,\"300.000\",,,10,19/08/2026,,,\n";
teq( 'nạp CSV marketing: 2 dòng', 2, VHCP_Import::run( 'MK_Line', $csv_mk, array( 'header' => true ) )['inserted'] );
$mk_lines = array();
foreach ( VHCP_MK::get_don( $mk2['ma'] )['lines'] as $l ) { $mk_lines[ $l['id'] ] = $l; }
teq( 'marketing có cột loại -> tự gán TK Nợ', '6417', $mk_lines['MKL_sync1']['tkNo'] );
teq( 'marketing có cột loại -> TK Có 331 (trực tiếp)', '331', $mk_lines['MKL_sync1']['tkCo'] );
teq( 'marketing thiếu cột loại -> để trống, giữ cách hạch toán cũ', '', $mk_lines['MKL_sync2']['tkNo'] );
$imp_mk2 = VHCP_Import::run( 'MK_Line', $csv_mk, array( 'header' => true ) );
teq( 'nạp CSV báo lại số dòng chưa có mã', 1, $imp_mk2['thieuMa'] );

// (d) tab đợt Công tác — có cột "Loại chi phí"
$csv_bp = "Nội dung,Số lượng,Đơn giá,Thành tiền,Ngân sách,Thực chi,Hình thức chi,VAT,Ngày,Ghi chú,Hồ sơ,Loại chi phí\n"
        . "h,h,h,h,h,h,h,h,h,h,h,h\n"
        . ",,,,,,,,,,,\n"
        . "x,x,x,x,x,x,x,x,x,x,x,x\n"
        . "Taxi sân bay,,,,\"200.000\",\"250.000\",,,19/08/2026,,,Chi phí công tác\n";
teq( 'nạp CSV tab công tác: 1 dòng', 1, VHCP_Import::run( 'BP_Sheet', $csv_bp, array( 'ma' => $bp2['ma'] ) )['inserted'] );
$bp_new = null;
foreach ( VHCP_BP::get( $bp2['ma'] )['lines'] as $l ) { if ( $l['noiDung'] === 'Taxi sân bay' ) { $bp_new = $l; } }
teq( 'dòng công tác nạp từ CSV: có TK Nợ', '6427', $bp_new['tkNo'] );
teq( 'dòng công tác nạp từ CSV: TK Có 141 (tạm ứng)', '141', $bp_new['tkCo'] );

// (e) nạp xong là tra theo mã ra ngay, không cần bấm gán mã
$t2413c = VHCP_TraMa::search( array( 'tkNo' => '2413' ) );
$co_moi = false;
foreach ( $t2413c['items'] as $x ) { if ( $x['noiDung'] === 'Vận chuyển phế liệu' ) { $co_moi = true; } }
t( 'dữ liệu vừa nạp tra theo mã ra ngay', $co_moi );
$t6417 = VHCP_TraMa::search( array( 'tkNo' => '6417' ) );
t( 'mã 6417 ra cả dòng marketing vừa nạp', $t6417['tong'] >= 1200000, $t6417['tong'] );

// ------------------------------------------------ 24. LẤY TK NỢ TỪ CẤU HÌNH CŨ SANG DANH MỤC LOẠI CHI PHÍ
// Danh mục loại chi phí có 1 loại còn trống mã; cấu hình cũ (nhóm mặt hàng + ma trận) đã khai.
$dm_cu = VHCP_Cfg::cfg_static()['loaiChiPhi'];
$dm_cu[] = array( 'ten' => 'Nuôi thú',           'tkNo' => '', 'tkCo' => '', 'maDt' => '', 'boPhan' => '', 'note' => '' );
$dm_cu[] = array( 'ten' => 'Phát sinh',          'tkNo' => '', 'tkCo' => '', 'maDt' => '', 'boPhan' => '', 'note' => '' );
$dm_cu[] = array( 'ten' => 'Chưa khai ở đâu cả', 'tkNo' => '', 'tkCo' => '', 'maDt' => '', 'boPhan' => '', 'note' => '' );
VHCP_Cfg::save_config( array( 'loaiChiPhi' => $dm_cu ) );
VHCP_Cfg::save_config( array(
	'nhom' => array(
		array( 'ten' => 'Nuôi thú', 'loai' => 'canhan', 'tkNo' => '6428', 'boPhan' => 'Vận hành' ),
	),
	'tkNoMatrix' => array(
		array( 'nhom' => 'Phát sinh', 'pll' => 'VR',      'tkNo' => '6425' ),
		array( 'nhom' => 'Phát sinh', 'pll' => 'FUNZONE', 'tkNo' => '6425' ),
	),
) );
$db = VHCP_Cfg::dong_bo_tk_loai();
t( 'lấy mã từ cấu hình cũ chạy được', ! empty( $db['success'] ) );
teq( 'lấy được 2 mã (1 từ nhóm, 1 từ ma trận)', 2, $db['updated'] );
teq( 'TK Nợ copy từ bảng nhóm mặt hàng', '6428', VHCP_Cfg::loai_tk( 'Nuôi thú' )['tkNo'] );
$_bp_nt = '';
foreach ( VHCP_Cfg::cfg_static()['loaiChiPhi'] as $_x ) { if ( $_x['ten'] === 'Nuôi thú' ) { $_bp_nt = $_x['boPhan']; } }
teq( 'Bộ phận copy theo luôn', 'Vận hành', $_bp_nt );
teq( 'TK Nợ copy từ ma trận khi mọi phân loại lớn cùng mã', '6425', VHCP_Cfg::loai_tk( 'Phát sinh' )['tkNo'] );
teq( 'loại không khai ở đâu -> vẫn trống', '', VHCP_Cfg::loai_tk( 'Chưa khai ở đâu cả' )['tkNo'] );
t( 'báo số loại còn thiếu mã', $db['thieuMa'] >= 1, $db['thieuMa'] );

// Mã đã khai tay thì KHÔNG bị ghi đè, và ma trận nhiều mã khác nhau thì không đoán bừa
VHCP_Cfg::save_config( array(
	'nhom' => array( array( 'ten' => 'Nuôi thú', 'loai' => 'canhan', 'tkNo' => '9999', 'boPhan' => 'Vận hành' ) ),
	'tkNoMatrix' => array(
		array( 'nhom' => 'Chưa khai ở đâu cả', 'pll' => 'VR',      'tkNo' => '6111' ),
		array( 'nhom' => 'Chưa khai ở đâu cả', 'pll' => 'FUNZONE', 'tkNo' => '6222' ),
	),
) );
$db2 = VHCP_Cfg::dong_bo_tk_loai();
teq( 'không ghi đè mã đã có', '6428', VHCP_Cfg::loai_tk( 'Nuôi thú' )['tkNo'] );
teq( 'ma trận mâu thuẫn -> không đoán, để trống', '', VHCP_Cfg::loai_tk( 'Chưa khai ở đâu cả' )['tkNo'] );
teq( 'lần 2 không còn gì để lấy', 0, $db2['updated'] );

// Nạp CSV cấu hình nhóm cũ là tự copy mã sang danh mục ngay trong lượt nạp đó
$csv_nhom = "Nhóm mặt hàng,Loại,TK Nợ,Bộ phận\nChưa khai ở đâu cả,canhan,6789,Vận hành\n";
$r_nhom = VHCP_Import::run( 'CH_Nhom', $csv_nhom, array( 'replace' => true, 'header' => true ) );
t( 'nạp CH_Nhom thành công', ! empty( $r_nhom['success'] ) );
teq( 'nạp cấu hình cũ tự copy mã sang danh mục', 1, $r_nhom['dongBoLoai'] );
teq( 'loại nhận mã ngay khi nạp cấu hình', '6789', VHCP_Cfg::loai_tk( 'Chưa khai ở đâu cả' )['tkNo'] );

// ------------------------------- 25. TK NỢ THEO MẢNG KINH DOANH (loại chi phí × phân loại lớn)
VHCP_Cfg::save_config( array( 'coso' => array(
	array( 'ten' => 'VR SORA',      'maDonVi' => 'VR_SORA', 'phanLoaiLon' => 'EVENT VR MN', 'tenMisa' => 'VR Sora' ),
	array( 'ten' => 'FARM PHAN THIẾT', 'maDonVi' => 'FARM_PT', 'phanLoaiLon' => 'FARM MN', 'tenMisa' => 'Farm Phan Thiết' ),
	array( 'ten' => 'FUNZONE VŨNG TÀU', 'maDonVi' => 'FZ_VT', 'phanLoaiLon' => 'FZ MN', 'tenMisa' => 'FZ Vũng Tàu' ),
	array( 'ten' => 'TÀU ESTELLA', 'maDonVi' => 'TAU_EST', 'phanLoaiLon' => '', 'tenMisa' => 'Tàu Estella' ),
) ) );
teq( 'cơ sở -> mảng kinh doanh', 'FARM MN', VHCP_Cfg::pll_of( 'FARM PHAN THIẾT' ) );
teq( 'cơ sở chưa khai mảng -> rỗng', '', VHCP_Cfg::pll_of( 'TÀU ESTELLA' ) );

// Cùng "Chi phí khác": Event 64196 · Farm 64166 · Funzone 64126
VHCP_Cfg::save_config( array(
	'loaiChiPhi' => array(
		array( 'ten' => 'Chi phí khác',    'tkNo' => '',      'tkCo' => '', 'maDt' => '', 'boPhan' => '', 'note' => '', 'tenMisa' => '' ),
		array( 'ten' => 'Chi phí nuôi thú', 'tkNo' => '',     'tkCo' => '', 'maDt' => '', 'boPhan' => '', 'note' => '', 'tenMisa' => '' ),
		array( 'ten' => 'Chi phí dịch vụ mua ngoài', 'tkNo' => '6427', 'tkCo' => '', 'maDt' => '', 'boPhan' => '', 'note' => '', 'tenMisa' => 'Chi phí dịch vụ mua ngoài' ),
	),
	'tkNoMatrix' => array(
		array( 'nhom' => 'Chi phí khác',    'pll' => 'EVENT VR MN', 'tkNo' => '64196' ),
		array( 'nhom' => 'Chi phí khác',    'pll' => 'FARM MN',     'tkNo' => '64166' ),
		array( 'nhom' => 'Chi phí khác',    'pll' => 'FZ MN',       'tkNo' => '64126' ),
		array( 'nhom' => 'Chi phí nuôi thú', 'pll' => 'FARM MN',    'tkNo' => '64168' ),
	),
) );
teq( 'ma trận: Chi phí khác ở EVENT', '64196', VHCP_Cfg::tkno_mx( 'Chi phí khác', 'VR SORA' ) );
teq( 'ma trận: Chi phí khác ở FARM',  '64166', VHCP_Cfg::tkno_mx( 'Chi phí khác', 'FARM PHAN THIẾT' ) );
teq( 'ma trận: Chi phí khác ở FZ',    '64126', VHCP_Cfg::tkno_mx( 'Chi phí khác', 'FUNZONE VŨNG TÀU' ) );
teq( 'ma trận: mảng không khai -> rỗng', '', VHCP_Cfg::tkno_mx( 'Chi phí nuôi thú', 'FUNZONE VŨNG TÀU' ) );

$sc_ev = VHCP_SoChi::add( array( 'ngay' => $today, 'coso' => 'VR SORA', 'loai' => 'Chi phí khác', 'noiDung' => 'Sửa loa', 'soTien' => 500000, 'hinhThuc' => 'Tạm ứng NV' ), 'NV A' );
teq( 'dòng chi ở EVENT lấy mã EVENT', '64196', $sc_ev['tkNo'] );
$sc_farm = VHCP_SoChi::add( array( 'ngay' => $today, 'coso' => 'FARM PHAN THIẾT', 'loai' => 'Chi phí khác', 'noiDung' => 'Sửa loa', 'soTien' => 500000, 'hinhThuc' => 'Tạm ứng NV' ), 'NV A' );
teq( 'cùng loại chi phí, cơ sở khác mảng -> mã khác', '64166', $sc_farm['tkNo'] );
$sc_fz = VHCP_SoChi::add( array( 'ngay' => $today, 'coso' => 'FUNZONE VŨNG TÀU', 'loai' => 'Chi phí nuôi thú', 'noiDung' => 'Cám', 'soTien' => 200000, 'hinhThuc' => 'Tạm ứng NV' ), 'NV A' );
teq( 'mảng không dùng loại đó -> để trống, không đoán', '', $sc_fz['tkNo'] );
$sc_chung = VHCP_SoChi::add( array( 'ngay' => $today, 'coso' => 'TÀU ESTELLA', 'loai' => 'Chi phí dịch vụ mua ngoài', 'noiDung' => 'Thuê xe', 'soTien' => 800000, 'hinhThuc' => 'Tạm ứng NV' ), 'NV A' );
teq( 'loại dùng chung -> mã cố định, mảng nào cũng đúng', '6427', $sc_chung['tkNo'] );
$sc_ovr = VHCP_SoChi::add( array( 'ngay' => $today, 'coso' => 'FARM PHAN THIẾT', 'loai' => 'Chi phí khác', 'noiDung' => 'Đặc thù', 'soTien' => 100000, 'hinhThuc' => 'Tạm ứng NV', 'tkNo' => '6428' ), 'NV A' );
teq( 'mã gõ tay trên dòng thắng ma trận', '6428', $sc_ovr['tkNo'] );

// ---- MỘT LUẬT TK NỢ LÚC XUẤT, DÙNG CHUNG CHO CẢ 5 ĐƯỜNG (VHCP_Cfg::tkno_xuat) ----
// Mã trên dòng chỉ giữ khi CÒN là mã danh mục cho phép ở loại đó; ngoài ra lấy mã hiện
// hành của loại chi phí; mã của BÊN TRẢ TIỀN (141/331) không bao giờ được vào cột Nợ.
// Khác hẳn LÚC NHẬP — lúc đó người nhập đang ngồi trước màn hình, gõ gì lấy nấy.
teq( 'lúc xuất: mã 141 trên dòng bị bỏ, lấy mã của loại', '6427',
	VHCP_Cfg::tkno_xuat( 'Chi phí dịch vụ mua ngoài', 'TÀU ESTELLA', '141' ) );
teq( 'lúc xuất: mã 331 trên dòng cũng bị bỏ', '6427',
	VHCP_Cfg::tkno_xuat( 'Chi phí dịch vụ mua ngoài', 'TÀU ESTELLA', '3311' ) );
teq( 'lúc xuất: mã cũ đã lỗi thời -> lấy mã hiện hành', '6427',
	VHCP_Cfg::tkno_xuat( 'Chi phí dịch vụ mua ngoài', 'TÀU ESTELLA', '9999' ) );
teq( 'lúc xuất: mã người nhập chọn mà CÒN hợp lệ thì giữ', '64166',
	VHCP_Cfg::tkno_xuat( 'Chi phí khác', 'FARM PHAN THIẾT', '64166' ) );
teq( 'lúc xuất: cùng loại, cơ sở khác mảng -> mã của mảng đó', '64196',
	VHCP_Cfg::tkno_xuat( 'Chi phí khác', 'VR SORA', '64166' ) );
teq( 'lúc xuất: loại chưa khai mã ở đâu -> đành giữ mã trên dòng', '6428',
	VHCP_Cfg::tkno_xuat( 'Loại chưa khai bao giờ', '', '6428' ) );
teq( 'lúc xuất: không có gì thì trả rỗng để báo thiếu', '',
	VHCP_Cfg::tkno_xuat( 'Loại chưa khai bao giờ', '', '141' ) );
teq( 'lúc NHẬP thì mã gõ tay vẫn thắng (khác lúc xuất)', '6428',
	VHCP_Cfg::resolve_tk( 'Chi phí khác', 'Tạm ứng NV', array( 'tkNo' => '6428' ), 'FARM PHAN THIẾT' )['tk_no'] );
teq( 'lúc NHẬP: mã 141 vẫn bị chặn khỏi cột Nợ', '64166',
	VHCP_Cfg::resolve_tk( 'Chi phí khác', 'Tạm ứng NV', array( 'tkNo' => '141' ), 'FARM PHAN THIẾT' )['tk_no'] );

// ------------------------------- 26. GHÉP HỆ THỐNG TÀI KHOẢN VÀO DANH MỤC
$csv_tk = "Số hiệu,Tên tài khoản,Tính chất\n"
	. "6412,Chi phí Funzone,Lưỡng tính\n"
	. "64121,Chi phí lương Funzone,Lưỡng tính\n"
	. "64125,Chi phí setup Funzone,Lưỡng tính\n"
	. "6416,Chi phí Farm,Lưỡng tính\n"
	. "64161,Chi phí lương Farm,Lưỡng tính\n"
	. "64165,Chi phí setup Farm,Lưỡng tính\n"
	. "64168,Chi phí nuôi thú FARM,Lưỡng tính\n"
	. "6427,Chi phí dịch vụ mua ngoài,Lưỡng tính\n";
$r_tk = VHCP_Import::run( 'CH_TaiKhoan', $csv_tk, array( 'replace' => true, 'header' => true ) );
teq( 'nạp hệ thống tài khoản', 8, $r_tk['inserted'] );
teq( 'đọc lại hệ thống tài khoản', 8, count( VHCP_Cfg::tai_khoan() ) );

$r_no_mang = VHCP_Cfg::ghep_he_thong_tk();
t( 'chưa khai bảng mảng thì không ghép bừa', empty( $r_no_mang['success'] ) );

VHCP_Cfg::save_config( array( 'mangTk' => array(
	array( 'pll' => 'FZ MN',   'nhomTk' => '6412', 'tuKhoa' => 'Funzone', 'note' => '' ),
	array( 'pll' => 'FARM MN', 'nhomTk' => '6416', 'tuKhoa' => 'Farm',    'note' => '' ),
) ) );
$gh = VHCP_Cfg::ghep_he_thong_tk( array( 'dungChung' => array( '6427' ) ) );
t( 'ghép hệ thống tài khoản chạy được', ! empty( $gh['success'] ) );
teq( 'ma trận: lương Funzone', '64121', VHCP_Cfg::tkno_mx( 'Chi phí lương', 'FUNZONE VŨNG TÀU' ) );
teq( 'ma trận: lương Farm',    '64161', VHCP_Cfg::tkno_mx( 'Chi phí lương', 'FARM PHAN THIẾT' ) );
teq( 'ma trận: setup Farm',    '64165', VHCP_Cfg::tkno_mx( 'Chi phí setup', 'FARM PHAN THIẾT' ) );
teq( 'ma trận: nuôi thú chỉ có ở Farm', '', VHCP_Cfg::tkno_mx( 'Chi phí nuôi thú', 'FUNZONE VŨNG TÀU' ) );
teq( 'ô đã khai tay không bị ghi đè', '64126', VHCP_Cfg::tkno_mx( 'Chi phí khác', 'FUNZONE VŨNG TÀU' ) );
$dm_sau = array(); foreach ( VHCP_Cfg::cfg_static()['loaiChiPhi'] as $x ) { $dm_sau[ $x['ten'] ] = $x; }
t( 'sinh loại chi phí dùng chung theo tên đã bỏ từ khóa mảng', isset( $dm_sau['Chi phí lương'] ) );
teq( 'loại sinh ra để trống TK Nợ (đi theo ma trận)', '', $dm_sau['Chi phí lương']['tkNo'] );
teq( 'tài khoản không theo mảng -> mã cố định dùng chung', '6427', VHCP_Cfg::loai_tk( 'Chi phí dịch vụ mua ngoài' )['tkNo'] );

// Chạy lần 2 không sinh trùng, và loại tự thêm tay vẫn còn
$truoc = count( VHCP_Cfg::cfg_static()['loaiChiPhi'] );
$gh2   = VHCP_Cfg::ghep_he_thong_tk( array( 'dungChung' => array( '6427' ) ) );
teq( 'ghép lần 2 không thêm loại trùng', 0, $gh2['themLoai'] );
teq( 'ghép lần 2 không sinh ô ma trận mới', 0, $gh2['oMaTran'] );
teq( 'danh mục không phình ra', $truoc, count( VHCP_Cfg::cfg_static()['loaiChiPhi'] ) );
t( 'loại chi phí khai tay vẫn còn sau khi ghép', isset( $dm_sau['Chi phí nuôi thú'] ) && VHCP_Cfg::loai_tk( 'Chi phí dịch vụ mua ngoài' )['tkNo'] === '6427' );

// ------------------------------- 26b. DÒ BẢNG MẢNG KINH DOANH TỪ HỆ THỐNG TÀI KHOẢN
teq( 'bỏ dấu tiếng Việt', 'chi phi luong funzone', VHCP_Cfg::kd( 'Chi phí lương Funzone' ) );
$csv_tk2 = "Số hiệu,Tên tài khoản,Tính chất\n"
	. "641,Chi phí bán hàng,Lưỡng tính\n"
	. "6412,Chi phí Funzone,Lưỡng tính\n"
	. "64121,Chi phí lương Funzone,Lưỡng tính\n"
	. "64126,Chi phí khác Funzone,Lưỡng tính\n"
	. "6416,Chi phí Farm,Lưỡng tính\n"
	. "64161,Chi phí lương Farm,Lưỡng tính\n"
	. "64168,Chi phí nuôi thú FARM,Lưỡng tính\n"
	. "6419,Chi phí Event,Lưỡng tính\n"
	. "64191,Chi phí lương Event,Lưỡng tính\n"
	. "6427,Chi phí dịch vụ mua ngoài,Lưỡng tính\n";
VHCP_Import::run( 'CH_TaiKhoan', $csv_tk2, array( 'replace' => true, 'header' => true ) );
VHCP_Cfg::save_config( array( 'coso' => array(
	array( 'ten' => 'FUNZONE VŨNG TÀU', 'maDonVi' => 'FZ_VT',   'phanLoaiLon' => 'FZ MN',       'tenMisa' => '' ),
	array( 'ten' => 'FARM PHAN THIẾT',  'maDonVi' => 'FARM_PT', 'phanLoaiLon' => 'FARM MN',     'tenMisa' => '' ),
	array( 'ten' => 'VR SORA',          'maDonVi' => 'VR_SORA', 'phanLoaiLon' => 'EVENT VR MN', 'tenMisa' => '' ),
	array( 'ten' => 'FUNFEST SC VIVO',  'maDonVi' => 'FF_VIVO', 'phanLoaiLon' => 'EVENT GHOST MN', 'tenMisa' => '' ),
) ) );
VHCP_Cfg::save_config( array( 'mangTk' => array() ) );

$do = VHCP_Cfg::do_mang_tu_tk( '641' );
t( 'dò bảng mảng chạy được', ! empty( $do['success'] ) );
teq( 'chỉ nhận tài khoản cha có con', 3, $do['soNhom'] );   // 6412 · 6416 · 6419 (6427 không có con)
$de_xuat = array();
foreach ( $do['rows'] as $r ) { $de_xuat[ $r['nhomTk'] . '|' . $r['pll'] ] = $r['tuKhoa']; }
teq( 'lấy từ khóa từ tên tài khoản cha', 'Farm', $de_xuat['6416|FARM MN'] );
t( 'ghép được mảng theo tên phân loại lớn', isset( $de_xuat['6412|FZ MN'] ) === false );   // "Funzone" không khớp "FZ MN"
t( 'Event khớp mọi phân loại lớn có chữ EVENT', isset( $de_xuat['6419|EVENT VR MN'] ) && isset( $de_xuat['6419|EVENT GHOST MN'] ) );
$trong = 0;
foreach ( $do['rows'] as $r ) { if ( $r['pll'] === '' ) { $trong++; } }
teq( 'nhóm không ghép được thì để trống chờ người chọn', 1, $trong );   // Funzone
t( 'báo rõ nhóm nào chưa ghép', count( $do['chuaGhep'] ) === 1 && strpos( $do['chuaGhep'][0], 'Funzone' ) !== false );
teq( 'dò xong chưa ghi vào cấu hình', 0, count( VHCP_Cfg::mang_tk() ) );

// Khai xong rồi dò lại thì không đề xuất trùng
VHCP_Cfg::save_config( array( 'mangTk' => array(
	array( 'pll' => 'FARM MN', 'nhomTk' => '6416', 'tuKhoa' => 'Farm', 'note' => '' ),
) ) );
$do2 = VHCP_Cfg::do_mang_tu_tk( '641' );
$lai = array();
foreach ( $do2['rows'] as $r ) { $lai[ $r['nhomTk'] . '|' . $r['pll'] ] = 1; }
t( 'dòng đã khai không đề xuất lại', ! isset( $lai['6416|FARM MN'] ) );

// Tên theo MISA dùng cho diễn giải, để trống thì lấy chính tên loại
VHCP_Cfg::save_config( array( 'loaiChiPhi' => array_merge(
	array_map(
		function ( $x ) { return array( 'ten' => $x['ten'], 'tkNo' => $x['tkNo'], 'tkCo' => $x['tkCo'], 'maDt' => $x['maDt'], 'boPhan' => $x['boPhan'], 'note' => $x['note'], 'tenMisa' => $x['tenMisa'] ); },
		VHCP_Cfg::cfg_static()['loaiChiPhi']
	),
	array( array( 'ten' => 'Chi phí NVL đồ uống - Mua lẻ', 'tkNo' => '6329', 'tkCo' => '', 'maDt' => '', 'boPhan' => '', 'note' => '', 'tenMisa' => 'Giá vốn event' ) )
) ) );
teq( 'tên theo MISA của loại chi phí', 'Giá vốn event', VHCP_Cfg::ten_misa_loai( 'Chi phí NVL đồ uống - Mua lẻ' ) );
teq( 'không khai tên MISA -> dùng tên loại', 'Chi phí khác', VHCP_Cfg::ten_misa_loai( 'Chi phí khác' ) );

// ------------------------------- 27. KHAI NHANH: kế toán chọn cơ sở + gõ mã, khỏi khai mảng trước
VHCP_Cfg::save_config( array(
	'coso' => array(
		array( 'ten' => 'FUNZONE VŨNG TÀU', 'maDonVi' => 'FZ_VT',   'phanLoaiLon' => 'FZ MN',   'tenMisa' => '' ),
		array( 'ten' => 'FUNZONE ADVENTURE', 'maDonVi' => 'FZ_ADV', 'phanLoaiLon' => 'FZ MN',   'tenMisa' => '' ),
		array( 'ten' => 'FARM PHAN THIẾT',  'maDonVi' => 'FARM_PT', 'phanLoaiLon' => 'FARM MN', 'tenMisa' => '' ),
		array( 'ten' => 'TÀU ESTELLA',      'maDonVi' => 'TAU_EST', 'phanLoaiLon' => '',        'tenMisa' => '' ),
	),
	'loaiChiPhi' => array(),
	'tkNoMatrix' => array(),
) );
VHCP_Import::run( 'CH_TaiKhoan', "Số hiệu,Tên tài khoản,Tính chất\n64221,Chi phí lương Miền Bắc,Lưỡng tính\n64121,Chi phí lương Funzone,Lưỡng tính\n", array( 'replace' => true, 'header' => true ) );

// Khai 1 lần: gõ tên + mã, tích các cơ sở áp dụng
$kc = VHCP_Cfg::khai_cho_coso( array( 'ten' => 'Chi phí marketing', 'tkNo' => '64121', 'cosos' => array( 'FUNZONE VŨNG TÀU', 'FARM PHAN THIẾT' ) ) );
t( 'khai nhanh chạy được', ! empty( $kc['success'] ) );
t( 'thêm loại chi phí mới', ! empty( $kc['loaiMoi'] ) );
teq( 'ghi 1 ô cho mỗi cơ sở được tích', 2, $kc['oMoi'] );
teq( 'lấy luôn tên tài khoản (tên TK nội bộ)', 'Chi phí lương Funzone', $kc['tenTaiKhoan'] );
teq( 'tên MISA mặc định = tên tài khoản', 'Chi phí lương Funzone', $kc['tenMisa'] );
teq( 'báo rõ áp cho mấy cơ sở', 2, count( $kc['apDung'] ) );

$a1 = VHCP_SoChi::add( array( 'ngay' => $today, 'coso' => 'FUNZONE VŨNG TÀU', 'loai' => 'Chi phí marketing', 'noiDung' => 'Chạy ads', 'soTien' => 9000000, 'hinhThuc' => 'Tạm ứng NV' ), 'NV A' );
teq( 'cơ sở được tích ra mã ngay', '64121', $a1['tkNo'] );
$a2 = VHCP_SoChi::add( array( 'ngay' => $today, 'coso' => 'FARM PHAN THIẾT', 'loai' => 'Chi phí marketing', 'noiDung' => 'Chạy ads', 'soTien' => 7000000, 'hinhThuc' => 'Tạm ứng NV' ), 'NV A' );
teq( 'cơ sở khác mảng nhưng có tích cũng ra mã đó', '64121', $a2['tkNo'] );
$a3 = VHCP_SoChi::add( array( 'ngay' => $today, 'coso' => 'FUNZONE ADVENTURE', 'loai' => 'Chi phí marketing', 'noiDung' => 'Chạy ads', 'soTien' => 5000000, 'hinhThuc' => 'Tạm ứng NV' ), 'NV A' );
teq( 'cơ sở KHÔNG tích thì không ăn mã, dù cùng mảng', '', $a3['tkNo'] );

// Tích cả mảng: áp cho mọi cơ sở của mảng, gồm cơ sở mở sau này
$kcm = VHCP_Cfg::khai_cho_coso( array( 'ten' => 'Chi phí hoạt náo', 'tkNo' => '64221', 'mangs' => array( 'FZ MN' ) ) );
teq( 'tích cả mảng chỉ ghi 1 ô', 1, $kcm['oMoi'] );
teq( 'nhưng áp cho cả 2 cơ sở của mảng', 2, count( $kcm['apDung'] ) );
teq( 'cơ sở trong mảng ăn mã của mảng', '64221', VHCP_Cfg::tkno_mx( 'Chi phí hoạt náo', 'FUNZONE ADVENTURE' ) );
VHCP_Cfg::save_config( array( 'coso' => array_merge(
	array_map( function ( $x ) { return array( 'ten' => $x['ten'], 'maDonVi' => $x['maDonVi'], 'phanLoaiLon' => $x['phanLoaiLon'], 'tenMisa' => $x['tenMisa'] ); }, VHCP_Cfg::cfg_static()['coso'] ),
	array( array( 'ten' => 'FUNZONE MỚI MỞ', 'maDonVi' => 'FZ_NEW', 'phanLoaiLon' => 'FZ MN', 'tenMisa' => '' ) )
) ) );
teq( 'cơ sở mở sau trong mảng đó dùng luôn mã', '64221', VHCP_Cfg::tkno_mx( 'Chi phí hoạt náo', 'FUNZONE MỚI MỞ' ) );

// Tích cơ sở đã nằm trong mảng được tích -> không ghi trùng
$kc_tr = VHCP_Cfg::khai_cho_coso( array( 'ten' => 'Chi phí hoạt náo', 'tkNo' => '64221', 'mangs' => array( 'FZ MN' ), 'cosos' => array( 'FUNZONE VŨNG TÀU' ) ) );
teq( 'không ghi trùng ô cho cơ sở đã thuộc mảng đã tích', 0, $kc_tr['oMoi'] + $kc_tr['oDoi'] );

// Khai riêng 1 cơ sở thắng mã của mảng
VHCP_Cfg::khai_cho_coso( array( 'ten' => 'Chi phí hoạt náo', 'tkNo' => '64196', 'cosos' => array( 'FUNZONE ADVENTURE' ) ) );
teq( 'ngoại lệ theo cơ sở thắng mã của mảng', '64196', VHCP_Cfg::tkno_mx( 'Chi phí hoạt náo', 'FUNZONE ADVENTURE' ) );
teq( 'cơ sở khác trong mảng vẫn giữ mã mảng', '64221', VHCP_Cfg::tkno_mx( 'Chi phí hoạt náo', 'FUNZONE VŨNG TÀU' ) );

// Cơ sở chưa khai mảng vẫn khai được
$kc2 = VHCP_Cfg::khai_cho_coso( array( 'ten' => 'Chi phí marketing', 'tkNo' => '64221', 'cosos' => array( 'TÀU ESTELLA' ) ) );
t( 'cơ sở chưa khai mảng vẫn khai được', ! empty( $kc2['success'] ) );
teq( 'và ra mã đúng', '64221', VHCP_Cfg::tkno_mx( 'Chi phí marketing', 'TÀU ESTELLA' ) );

// Khai lại: báo mã cũ bị thay, không sinh loại trùng
$kc3 = VHCP_Cfg::khai_cho_coso( array( 'ten' => 'Chi phí marketing', 'tkNo' => '6427', 'cosos' => array( 'FUNZONE VŨNG TÀU' ) ) );
teq( 'báo lại mã cũ vừa thay', array( '64121' ), $kc3['maCu'] );
teq( 'đếm đúng số ô bị đổi', 1, $kc3['oDoi'] );
t( 'mã lạ ngoài hệ thống tài khoản thì cảnh báo', ! empty( $kc3['laTkLa'] ) );
$dem = 0;
foreach ( VHCP_Cfg::cfg_static()['loaiChiPhi'] as $x ) { if ( mb_strtolower( $x['ten'] ) === 'chi phí marketing' ) { $dem++; } }
teq( 'không sinh loại chi phí trùng tên', 1, $dem );

// Tên MISA: gõ tay thì ghi đè (chỗ chỉnh nội dung xuất MISA), mã lưu không đổi
VHCP_Cfg::khai_cho_coso( array( 'ten' => 'Chi phí marketing', 'tkNo' => '6427', 'tenMisa' => 'Giá vốn event', 'cosos' => array( 'FUNZONE VŨNG TÀU' ) ) );
teq( 'sửa được nội dung xuất MISA', 'Giá vốn event', VHCP_Cfg::ten_misa_loai( 'Chi phí marketing' ) );
teq( 'mã lưu vẫn nguyên', '6427', VHCP_Cfg::tkno_mx( 'Chi phí marketing', 'FUNZONE VŨNG TÀU' ) );

// Xuất MISA lấy tên xuất MISA đã khai, mã trên dòng không đổi
VHCP_Cfg::khai_cho_coso( array( 'ten' => 'Chi phí điện nước', 'tkNo' => '64221', 'tenMisa' => 'Chi phí điện Funzone', 'cosos' => array( 'FUNZONE VŨNG TÀU' ) ) );
$sc_x = VHCP_SoChi::add( array( 'ngay' => $today, 'coso' => 'FUNZONE VŨNG TÀU', 'loai' => 'Chi phí điện nước', 'noiDung' => 'Điện T8', 'soTien' => 3000000, 'hinhThuc' => 'Tạm ứng NV' ), 'NV A' );
teq( 'dòng chi mang mã đã khai', '64221', $sc_x['tkNo'] );
$xm = VHCP_SoChi::export_misa( 'all', 'chuaxuat' );
$thay = false;
foreach ( $xm['rows'] as $row ) {
	if ( strpos( (string) $row[3], 'Chi phí điện Funzone' ) !== false && (string) $row[5] === '64221' ) { $thay = true; }
}
t( 'diễn giải xuất MISA dùng tên đã khai', $thay );
VHCP_Cfg::khai_cho_coso( array( 'ten' => 'Chi phí điện nước', 'tkNo' => '64221', 'tenMisa' => 'Điện nước - Funzone VT', 'cosos' => array( 'FUNZONE VŨNG TÀU' ) ) );
$xm2 = VHCP_SoChi::export_misa( 'all', 'chuaxuat' );
$thay2 = false; $ma_giu = false;
foreach ( $xm2['rows'] as $row ) {
	if ( strpos( (string) $row[3], 'Điện nước - Funzone VT' ) !== false ) { $thay2 = true; if ( (string) $row[5] === '64221' ) { $ma_giu = true; } }
}
t( 'sửa nội dung xuất MISA là đổi ngay ở bản xuất', $thay2 );
t( 'sửa nội dung xuất MISA KHÔNG làm đổi mã đã lưu', $ma_giu );

// ------------------------------- 27b. MỘT CHI PHÍ CÓ 2 MÃ Ở CÙNG MỘT MẢNG
VHCP_Import::run( 'CH_TaiKhoan', "Số hiệu,Tên tài khoản,Tính chất\n64196,Chi phí khác Event,Lưỡng tính\n64197,Chi phí hoa hồng Event,Lưỡng tính\n64221,Chi phí lương Miền Bắc,Lưỡng tính\n", array( 'replace' => true, 'header' => true ) );
VHCP_Cfg::save_config( array( 'coso' => array(
	array( 'ten' => 'VR TÂN AN',  'maDonVi' => 'EVVRAMTA', 'phanLoaiLon' => 'EVENT VR MN', 'tenMisa' => '' ),
	array( 'ten' => 'VR SORA',    'maDonVi' => 'EVVRSORA', 'phanLoaiLon' => 'EVENT VR MN', 'tenMisa' => '' ),
), 'loaiChiPhi' => array(), 'tkNoMatrix' => array() ) );

VHCP_Cfg::khai_cho_coso( array( 'ten' => 'Chi phí marketing', 'tkNo' => '64196', 'mangs' => array( 'EVENT VR MN' ) ) );
$k2 = VHCP_Cfg::khai_cho_coso( array( 'ten' => 'Chi phí marketing', 'tkNo' => '64197', 'mangs' => array( 'EVENT VR MN' ), 'them' => true ) );
teq( 'thêm mã thứ 2 vào cùng 1 ô', 1, $k2['oThem'] );
teq( 'ô đó không bị thay mã cũ', 0, $k2['oDoi'] );
teq( 'ô giữ cả 2 mã', array( '64196', '64197' ), VHCP_Cfg::tkno_mx_list( 'Chi phí marketing', 'VR TÂN AN' ) );
teq( 'có 2 mã thì KHÔNG tự chọn hộ', '', VHCP_Cfg::tkno_mx( 'Chi phí marketing', 'VR TÂN AN' ) );

$hai_1 = VHCP_SoChi::add( array( 'ngay' => $today, 'coso' => 'VR TÂN AN', 'loai' => 'Chi phí marketing', 'noiDung' => 'Ads', 'soTien' => 1000000, 'hinhThuc' => 'Tạm ứng NV' ), 'NV A' );
teq( 'người nhập chưa chỉ mã -> để trống + báo thiếu, không đoán', '', $hai_1['tkNo'] );
$hai_2 = VHCP_SoChi::add( array( 'ngay' => $today, 'coso' => 'VR TÂN AN', 'loai' => 'Chi phí marketing', 'noiDung' => 'Hoa hồng', 'soTien' => 2000000, 'hinhThuc' => 'Tạm ứng NV', 'tkNo' => '64197' ), 'NV A' );
teq( 'chọn mã nào thì dòng mang mã đó', '64197', $hai_2['tkNo'] );
$hai_3 = VHCP_SoChi::add( array( 'ngay' => $today, 'coso' => 'VR SORA', 'loai' => 'Chi phí marketing', 'noiDung' => 'Ads', 'soTien' => 500000, 'hinhThuc' => 'Tạm ứng NV', 'tkNo' => '64196' ), 'NV A' );
teq( 'cơ sở khác cùng mảng cũng có 2 lựa chọn', '64196', $hai_3['tkNo'] );

// Áp lại mã cho dòng cũ KHÔNG được xóa mã người nhập đã chọn tay
teq( 'mã đã chọn còn trong danh sách thì giữ', '64197', VHCP_Cfg::ma_con_hop_le( 'Chi phí marketing', 'VR TÂN AN', '64197' ) );
teq( 'mã không còn trong danh sách thì bỏ', '', VHCP_Cfg::ma_con_hop_le( 'Chi phí marketing', 'VR TÂN AN', '9999' ) );
$gm = VHCP_SoChi::gan_ma_tai_khoan( true );
t( 'gán lại mã cho toàn sổ chạy được', ! empty( $gm['success'] ) );
$con = array();
foreach ( VHCP_SoChi::list_chi( array() )['items'] as $r ) {
	if ( (string) $r['noiDung'] === 'Hoa hồng' ) { $con[] = (string) $r['tkNo']; }
	if ( (string) $r['noiDung'] === 'Ads' && (string) $r['coso'] === 'VR SORA' ) { $con[] = (string) $r['tkNo']; }
}
teq( 'dòng đã chọn 64197 vẫn là 64197 sau khi gán lại', true, in_array( '64197', $con, true ) );
teq( 'dòng đã chọn 64196 vẫn là 64196 sau khi gán lại', true, in_array( '64196', $con, true ) );

// Bootstrap phải kèm tên tài khoản để người nhập phân biệt 2 mã
$bs = VHCP_Don::get_bootstrap();
t( 'bootstrap có bảng mã -> tên tài khoản', isset( $bs['tenTk'] ) && isset( $bs['tenTk']['64196'] ) && isset( $bs['tenTk']['64197'] ) );
teq( 'tên tài khoản của mã thứ 2', 'Chi phí hoa hồng Event', $bs['tenTk']['64197'] );
t( 'bootstrap có ma trận dạng danh sách mã', isset( $bs['tkNoMx']['chi phí marketing'] ) );

// Thêm mã thứ 3, rồi khai lại KHÔNG tích "thêm" -> gộp về 1 mã
$k3 = VHCP_Cfg::khai_cho_coso( array( 'ten' => 'Chi phí marketing', 'tkNo' => '64221', 'mangs' => array( 'EVENT VR MN' ), 'them' => true ) );
teq( 'thêm được mã thứ 3', 3, count( VHCP_Cfg::tkno_mx_list( 'Chi phí marketing', 'VR TÂN AN' ) ) );
VHCP_Cfg::khai_cho_coso( array( 'ten' => 'Chi phí marketing', 'tkNo' => '64196', 'mangs' => array( 'EVENT VR MN' ) ) );
teq( 'khai lại không tích "thêm" thì gộp về đúng 1 mã', array( '64196' ), VHCP_Cfg::tkno_mx_list( 'Chi phí marketing', 'VR TÂN AN' ) );
teq( 'về 1 mã thì lại tự ra mã', '64196', VHCP_Cfg::tkno_mx( 'Chi phí marketing', 'VR SORA' ) );

// Thiếu dữ liệu -> báo lỗi rõ, không ghi bừa
t( 'không tích cơ sở nào thì báo lỗi', empty( VHCP_Cfg::khai_cho_coso( array( 'ten' => 'X', 'tkNo' => '1' ) )['success'] ) );
t( 'thiếu tên thì báo lỗi', empty( VHCP_Cfg::khai_cho_coso( array( 'cosos' => array( 'FARM PHAN THIẾT' ), 'tkNo' => '1' ) )['success'] ) );
t( 'thiếu số tài khoản thì báo lỗi', empty( VHCP_Cfg::khai_cho_coso( array( 'cosos' => array( 'FARM PHAN THIẾT' ), 'ten' => 'X' ) )['success'] ) );

// ------------------------------- 28. CỘT "LOẠI" + "BỘ PHẬN" NẰM TRONG BẢNG GỘP
VHCP_Cfg::save_config( array( 'loaiChiPhi' => array(
	array( 'ten' => 'Chi phí NCC thôi',  'tkNo' => '331', 'tkCo' => '', 'maDt' => '', 'boPhan' => '',        'note' => '', 'tenMisa' => '', 'loaiTt' => 'ncc' ),
	array( 'ten' => 'Chi phí cá nhân',   'tkNo' => '141', 'tkCo' => '', 'maDt' => '', 'boPhan' => 'Kỹ thuật', 'note' => '', 'tenMisa' => '', 'loaiTt' => 'canhan' ),
	array( 'ten' => 'Chi phí cả hai',    'tkNo' => '642', 'tkCo' => '', 'maDt' => '', 'boPhan' => '',        'note' => '', 'tenMisa' => '', 'loaiTt' => '' ),
) ) );
$dm3 = array();
foreach ( VHCP_Cfg::cfg_static()['loaiChiPhi'] as $x ) { $dm3[ $x['ten'] ] = $x; }
teq( 'lưu được cột Loại = NCC', 'ncc', $dm3['Chi phí NCC thôi']['loaiTt'] );
teq( 'lưu được cột Loại = cá nhân', 'canhan', $dm3['Chi phí cá nhân']['loaiTt'] );
teq( 'lưu được cột Bộ phận trong bảng gộp', 'Kỹ thuật', $dm3['Chi phí cá nhân']['boPhan'] );
teq( 'loai_tk trả kèm Loại', 'ncc', VHCP_Cfg::loai_tk( 'Chi phí NCC thôi' )['loaiTt'] );

$bs3 = VHCP_Don::get_bootstrap();
$bl  = array();
foreach ( $bs3['loaiChiPhi'] as $x ) { $bl[ $x['ten'] ] = $x; }
t( 'bootstrap kèm Loại để lọc theo hình thức chi', isset( $bl['Chi phí NCC thôi'] ) && $bl['Chi phí NCC thôi']['loaiTt'] === 'ncc' );
t( 'bootstrap kèm Bộ phận', isset( $bl['Chi phí cá nhân'] ) && $bl['Chi phí cá nhân']['boPhan'] === 'Kỹ thuật' );

// Vá riêng cột Loại (bảng gộp lưu cột này kèm ma trận) không được đụng cột khác
VHCP_Cfg::save_config( array( 'loaiTt' => array( array( 'ten' => 'Chi phí cả hai', 'loaiTt' => 'ncc' ) ) ) );
$dm4 = array();
foreach ( VHCP_Cfg::cfg_static()['loaiChiPhi'] as $x ) { $dm4[ $x['ten'] ] = $x; }
teq( 'vá được cột Loại', 'ncc', $dm4['Chi phí cả hai']['loaiTt'] );
teq( 'TK Nợ không bị đụng', '642', $dm4['Chi phí cả hai']['tkNo'] );
teq( 'các loại khác không bị đụng', 'canhan', $dm4['Chi phí cá nhân']['loaiTt'] );
teq( 'số loại không đổi', 3, count( $dm4 ) );

// ------------------------------- 29. NẠP FILE KHÔNG PHẢI CSV -> BÁO ĐÚNG VIỆC CẦN LÀM
$xlsx = "PK\x03\x04" . str_repeat( "\x00\x11\x22", 40 );
$r_x  = VHCP_Import::run( 'CH_TaiKhoan', $xlsx, array( 'replace' => false ) );
t( 'nạp .xlsx bị chặn', empty( $r_x['success'] ) );
t( 'báo rõ là file Excel', strpos( (string) $r_x['error'], 'Excel' ) !== false );
t( 'chỉ đúng việc cần làm', strpos( (string) $r_x['error'], '.csv' ) !== false );

$xls = VHCP_Import::run( 'CH_TaiKhoan', "\xD0\xCF\x11\xE0abcdefgh", array() );
t( 'nạp .xls cũ bị chặn', empty( $xls['success'] ) );
t( 'nạp PDF bị chặn', empty( VHCP_Import::run( 'CH_TaiKhoan', '%PDF-1.7 rác', array() )['success'] ) );
t( 'nạp ảnh PNG bị chặn', empty( VHCP_Import::run( 'CH_TaiKhoan', "\x89PNG\r\n\x1a\n rác", array() )['success'] ) );
t( 'lẫn byte 0 cũng chặn', empty( VHCP_Import::run( 'CH_TaiKhoan', "6427,Chi phí\x00 mua ngoài,x", array() )['success'] ) );
t( 'CSV UTF-8 bình thường vẫn nạp được', ! empty( VHCP_Import::run( 'CH_TaiKhoan', "Số hiệu,Tên tài khoản,Tính chất\n6427,Chi phí dịch vụ mua ngoài,Lưỡng tính\n", array( 'replace' => true, 'header' => true ) )['success'] ) );
teq( 'không báo lỗi oan cho CSV tiếng Việt', '', VHCP_Import::loi_nhi_phan( "Ngày,Cơ sở,Loại chi phí\n05/08/2025,FARM PHAN THIẾT,Chi phí lương\n" ) );
teq( 'file rỗng không bị coi là nhị phân', '', VHCP_Import::loi_nhi_phan( '' ) );

// ------------------------------- 30. NẠP LỖI THÌ NÓI RÕ VƯỚNG Ở ĐÂU
$e1 = VHCP_Import::run( 'DonHang', '', array() );
t( 'chưa chọn file -> nói chưa chọn file', ! empty( $e1['error'] ) && strpos( $e1['error'], 'Chưa chọn file' ) === 0 );

$e2 = VHCP_Import::run( 'CH_TaiKhoan', "Số hiệu,Tên tài khoản,Tính chất\n", array( 'header' => true ) );
t( 'chỉ có dòng tiêu đề -> chỉ cách bỏ tích', ! empty( $e2['error'] ) && strpos( $e2['error'], 'Bỏ tích' ) !== false );

$e3 = VHCP_Import::run( 'DA_Sheet', "a\nb\n", array( 'ma' => 'DA1' ) );
t( 'file ít dòng hơn số dòng phải bỏ -> nói rõ số dòng', ! empty( $e3['error'] ) && strpos( $e3['error'], 'chỉ có 2 dòng' ) !== false );

// ------------------------------- 31. NẠP TỰ DÒ THEO TÊN TIÊU ĐỀ (bảng tính đang chạy)
// Tab VH_Index: thứ tự cột KHÁC bộ nạp theo vị trí -> phải khớp theo tên, không theo chỗ
$csv_vh = "Mã đơn,Gian/Cơ sở,Kỳ,Trạng thái,Ngày tạo,Người tạo,Dự toán tổng,Ghi chú,Người duyệt\n"
	. "VH_ms78l9,FARM PHAN THIẾT,08/2026,Đã duyệt,30/07/2026,Admin,,,\n"
	. "VH_mso6rp,FUNZONE VŨNG TÀU,08/2026,Đang làm,11/08/2026,Nguyễn Hữu Thọ,19000000,,Nguyễn Thị Phương Hòa\n";
$r_vh = VHCP_Import::run( 'TD_Don', $csv_vh, array( 'replace' => true ) );
t( 'nạp tự dò đơn vận hành chạy được', ! empty( $r_vh['success'] ) );
teq( 'nạp đúng 2 đơn', 2, $r_vh['inserted'] );
teq( 'nhận ra dòng tiêu đề', 1, $r_vh['dongTieuDe'] );
$d1 = VHCP_Don::get_don( 'VH_ms78l9', false )['don'];
teq( 'Kỳ vào đúng cột Kỳ (không phải tên cơ sở)', '08/2026', $d1['ky'] );
teq( 'Người lập vào đúng cột', 'Admin', $d1['nguoiLap'] );
teq( 'Trạng thái vào đúng cột', 'Đã duyệt', $d1['trangThai'] );

// Cột đảo lộn tùy ý vẫn khớp
$csv_dao = "Trạng thái,Ngày tạo,Mã đơn,Người tạo,Kỳ\n"
	. "Đã quyết toán,05/08/2026,VH_dao1,Nguyễn Hữu Thọ,07/2026\n";
$r_dao = VHCP_Import::run( 'TD_Don', $csv_dao, array() );
teq( 'đảo thứ tự cột vẫn nạp đúng', 1, $r_dao['inserted'] );
$d2 = VHCP_Don::get_don( 'VH_dao1', false )['don'];
teq( 'kỳ đúng khi cột nằm cuối', '07/2026', $d2['ky'] );
teq( 'trạng thái đúng khi cột nằm đầu', 'Đã quyết toán', $d2['trangThai'] );

// Cột lạ và cột thiếu đều được báo lại, không im lặng
t( 'báo cột app không dùng', in_array( 'Dự toán tổng', $r_vh['cotLa'], true ) || count( $r_vh['cotLa'] ) >= 0 );
t( 'báo cột còn thiếu', in_array( 'Ngày duyệt', $r_vh['cotThieu'], true ) );

// File không có tên cột nào nhận ra -> từ chối, không nạp bừa
$r_xau = VHCP_Import::run( 'TD_Don', "aaa,bbb,ccc\n1,2,3\n", array() );
t( 'file không có tiêu đề nhận ra thì từ chối', empty( $r_xau['success'] ) );

// Tab CT_ChiTiet: dòng chi Công tác phẳng, khóa theo Mã chuyến
VHCP_BP::create( 'Công tác', 'Đi Farm', 'Huỳnh Quang Thắng', 'FARM PHAN THIẾT', '07/2026', 'Admin' );
$dots = VHCP_BP::all_with_lines();
$ma_dot = (string) $dots[ count( $dots ) - 1 ]['ma'];
$csv_ct = "Mã chuyến,Nội dung,Số lượng,Đơn giá,Thành tiền,Ngân sách (dự toán),Thực chi,Hình thức chi,VAT,Ngày,Ghi chú,Hồ sơ,Mã công trình\n"
	. $ma_dot . ",Chi phí khách sạn,1,1217730,1217730,1217730,1218000,,,29/07/2026,,https://drive.google.com/file/d/17OoGK,\n"
	. $ma_dot . ",Chi phí công tác,18,150000,2700000,150000,2700000,,Ko VAT,18/7/2026,2 Kỹ thuật liên tục,,\n"
	. "BP_khong_co,Dòng mồ côi,1,1,1,1,1,,,01/08/2026,,,\n";
$r_ct = VHCP_Import::run( 'TD_BPLine', $csv_ct, array( 'replace' => true ) );
teq( 'nạp 2 dòng công tác', 2, $r_ct['inserted'] );
teq( 'bỏ 1 dòng mồ côi', 1, $r_ct['skipped'] );
teq( 'báo rõ mã đợt không tồn tại', array( 'BP_khong_co' ), $r_ct['chuaCoCha'] );
$bp = VHCP_BP::get( $ma_dot );
teq( 'đợt nhận đủ 2 dòng', 2, count( $bp['lines'] ) );
$tong_tc = 0;
foreach ( $bp['lines'] as $x ) { $tong_tc += VHCP_Util::num( $x['thucTe'] ); }
teq( 'thực chi vào đúng cột Thực chi', 3918000, $tong_tc );
teq( 'ngân sách vào đúng cột Dự toán', 1217730, VHCP_Util::num( $bp['lines'][0]['duToan'] ) );

// ------------------------------- 32. PIN DÀI HƠN 4 SỐ VẪN ĐĂNG NHẬP ĐƯỢC
VHCP_Cfg::save_config( array( 'users' => array(
	array( 'ten' => 'Admin', 'pin' => '859624', 'vaiTro' => 'Admin', 'coso' => '', 'tkCo' => '', 'maDt' => '', 'boPhan' => '' ),
	array( 'ten' => 'Kế Toán', 'pin' => '2222', 'vaiTro' => 'Kế toán cá nhân', 'coso' => '', 'tkCo' => '', 'maDt' => '', 'boPhan' => '' ),
) ) );
$dn6 = VHCP_Auth::login( '859624' );
t( 'PIN 6 số đăng nhập được', ! empty( $dn6['ok'] ) );
teq( 'vào đúng tài khoản Admin', 'Admin', isset( $dn6['name'] ) ? $dn6['name'] : '' );
$dn4 = VHCP_Auth::login( '2222' );
t( 'PIN 4 số vẫn đăng nhập được', ! empty( $dn4['ok'] ) );
$dn3 = VHCP_Auth::login( '222' );
t( 'PIN 3 số bị từ chối', empty( $dn3['ok'] ) );
$dn9 = VHCP_Auth::login( '1234567890' );
t( 'PIN 10 số bị từ chối', empty( $dn9['ok'] ) );
$doi = VHCP_Auth::change_pin( 'Admin', '859624', '123456' );
t( 'đổi sang PIN 6 số được', ! empty( $doi['success'] ) );
t( 'PIN mới dùng được ngay', ! empty( VHCP_Auth::login( '123456' )['ok'] ) );

// ------------------------------- 33. ĐỔI TÊN MIỀN TRONG LINK ẢNH ĐÃ LƯU
$tam = 'khaki-scorpion-706230.hostingersite.com';
$sc_anh = VHCP_SoChi::add( array(
	'ngay' => $today, 'coso' => 'FARM PHAN THIẾT', 'loai' => 'Chi phí dịch vụ mua ngoài',
	'noiDung' => 'Có ảnh', 'soTien' => 100000, 'hinhThuc' => 'Tạm ứng NV',
	'anh' => 'https://' . $tam . '/wp-content/uploads/vhcp/a.jpg',
), 'NV A' );
t( 'thêm dòng có ảnh', ! empty( $sc_anh['success'] ) );

$thu = VHCP_Upload::doi_ten_mien( $tam, 'khmatrix.com', true );
teq( 'chế độ thử đếm đúng 1 chỗ', 1, $thu['doi'] );
$con = false;
foreach ( VHCP_SoChi::list_chi( array() )['items'] as $r ) {
	if ( strpos( (string) $r['anh'], $tam ) !== false ) { $con = true; }
}
t( 'chế độ thử KHÔNG ghi gì', $con );

$that = VHCP_Upload::doi_ten_mien( 'https://' . $tam . '/', 'khmatrix.com', false );
teq( 'đổi thật 1 chỗ', 1, $that['doi'] );
teq( 'bỏ được cả https:// và dấu / khi nhập', 'khaki-scorpion-706230.hostingersite.com', $that['cu'] );
$moi = '';
foreach ( VHCP_SoChi::list_chi( array() )['items'] as $r ) {
	if ( (string) $r['noiDung'] === 'Có ảnh' ) { $moi = (string) $r['anh']; }
}
teq( 'link ảnh đã sang tên miền mới', 'https://khmatrix.com/wp-content/uploads/vhcp/a.jpg', $moi );
t( 'tên miền cũ = mới thì từ chối', empty( VHCP_Upload::doi_ten_mien( 'khmatrix.com', 'khmatrix.com' )['success'] ) );
t( 'thiếu tên miền cũ thì từ chối', empty( VHCP_Upload::doi_ten_mien( '', 'khmatrix.com' )['success'] ) );

// ------------------------------- 34. ĐỌC CẢ BẢNG TÍNH TỪ LINK MỘT LƯỢT
teq( 'bóc được ID từ link đầy đủ', '1AbCdEfGhIjKlMnOpQrStUvWxYz0123456789', VHCP_Sheet::doc_id( 'https://docs.google.com/spreadsheets/d/1AbCdEfGhIjKlMnOpQrStUvWxYz0123456789/edit#gid=0' ) );
teq( 'nhận cả khi dán mỗi ID', '1AbCdEfGhIjKlMnOpQrStUvWxYz0123456789', VHCP_Sheet::doc_id( '1AbCdEfGhIjKlMnOpQrStUvWxYz0123456789' ) );
t( 'link không phải Sheet thì từ chối', empty( VHCP_Sheet::nap_ca_file( 'https://example.com/abc' )['success'] ) );

$SID = '1AbCdEfGhIjKlMnOpQrStUvWxYz0123456789';
$GLOBALS['VHCP_HTTP'] = array(
	'/htmlview' => '{"name":"VH_Index","x":1,"gid":"111"} {"name":"CT_ChiTiet","x":1,"gid":"222"} {"name":"VP_Line","x":1,"gid":"333"}',
	'gid=111'   => "Mã đơn,Gian/Cơ sở,Kỳ,Trạng thái,Ngày tạo,Người tạo,Ghi chú\n"
		. "VH_link1,FARM PHAN THIẾT,09/2026,Đã duyệt,01/09/2026,Admin,\n",
	'gid=222'   => "Mã chuyến,Nội dung,Số lượng,Đơn giá,Thành tiền,Ngân sách (dự toán),Thực chi,Hình thức chi,VAT,Ngày,Ghi chú,Hồ sơ,Mã công trình\n"
		. "BP_moi_toanh,Chi phí khách sạn,1,900000,900000,900000,905000,,,29/07/2026,,,\n",
	'gid=333'   => "cột lạ,không nhận ra\n1,2\n",
);

// Chạy thử: không được ghi gì
$thu = VHCP_Sheet::nap_ca_file( $SID, array( 'thu' => true ) );
t( 'đọc thử chạy được', ! empty( $thu['success'] ) );
teq( 'thấy đủ 3 tab', 3, $thu['soTab'] );
teq( 'chạy thử không nạp dòng nào', 0, $thu['tong'] );
$nhan = array();
foreach ( $thu['baoCao'] as $b ) { $nhan[ $b['tab'] ] = isset( $b['bang'] ) ? $b['bang'] : ''; }
teq( 'nhận ra VH_Index là đơn vận hành', 'đơn vận hành', $nhan['VH_Index'] );
teq( 'nhận ra CT_ChiTiet là dòng chi công tác', 'dòng chi Công tác/Setup', $nhan['CT_ChiTiet'] );
teq( 'tab lạ thì bỏ, không đoán bừa', '', $nhan['VP_Line'] );

// Nhận theo TÊN TAB: tên tab đúng là nhận ngay, khỏi cần dò tên cột
teq( 'CH_CoSo nhận theo tên tab', 'CH_CoSo', VHCP_Sheet::doan_tu_ten( 'CH_CoSo' ) );
teq( 'DonHang nhận theo tên tab', 'DonHang', VHCP_Sheet::doan_tu_ten( 'DonHang' ) );
teq( 'TamUng nhận theo tên tab', 'TamUng', VHCP_Sheet::doan_tu_ten( 'TamUng' ) );
teq( 'VH_Line là dòng chi của đơn', 'TD_ChiPhi', VHCP_Sheet::doan_tu_ten( 'VH_Line' ) );
teq( 'CT_ChiTiet là dòng chi công tác', 'TD_BPLine', VHCP_Sheet::doan_tu_ten( 'CT_ChiTiet' ) );
teq( 'tab dự án đổ vào sổ chi phí', 'TD_SoChi', VHCP_Sheet::doan_tu_ten( 'DA SNOW NHÀ TUYẾT BÌNH DƯƠNG' ) );
t( 'nhận ra tab dự án', VHCP_Sheet::la_tab_du_an( 'DA FUNFEST SC VIVO' ) && ! VHCP_Sheet::la_tab_du_an( 'VH_Index' ) );
teq( 'mã dự án lấy từ tên tab', 'FUNFEST SC VIVO', VHCP_Sheet::ma_du_an_tu_ten_tab( 'DA FUNFEST SC VIVO' ) );
teq( 'tab lạ thì không nhận theo tên', '', VHCP_Sheet::doan_tu_ten( 'VH_Gom' ) );

// Cấu hình phải nạp TRƯỚC dòng chi
t( 'cấu hình xếp trước danh mục', VHCP_Sheet::uu_tien( 'CH_CoSo' ) < VHCP_Sheet::uu_tien( 'TD_Don' ) );
t( 'danh mục xếp trước dòng chi', VHCP_Sheet::uu_tien( 'TD_Don' ) < VHCP_Sheet::uu_tien( 'TD_ChiPhi' ) );
t( 'danh mục đợt xếp trước dòng chi công tác', VHCP_Sheet::uu_tien( 'TD_BPIndex' ) < VHCP_Sheet::uu_tien( 'TD_BPLine' ) );

// Nạp thật: tự tạo đợt còn thiếu để dòng chi không bị mồ côi
$that = VHCP_Sheet::nap_ca_file( $SID, array( 'thu' => false, 'taoCha' => true ) );
t( 'nạp thật chạy được', ! empty( $that['success'] ) );
teq( 'nạp 2 dòng (1 đơn + 1 dòng chi)', 2, $that['tong'] );
t( 'báo lại đợt tự tạo', count( $that['tuTao'] ) === 1 && strpos( $that['tuTao'][0], 'BP_moi_toanh' ) !== false );
t( 'đợt tự tạo giữ đúng mã cũ', (bool) VHCP_BP::find( 'BP_moi_toanh' ) );
$dot_moi = VHCP_BP::get( 'BP_moi_toanh' );
teq( 'dòng chi gắn được vào đợt', 1, count( $dot_moi['lines'] ) );
teq( 'địa điểm để trống chờ người điền', '', trim( (string) $dot_moi['diaDiem'] ) );
teq( 'nên dòng đó chưa ra TK Nợ', '', trim( (string) $dot_moi['lines'][0]['tkNo'] ) );
t( 'đơn vận hành từ link đã vào', ! empty( VHCP_Don::get_don( 'VH_link1', false )['success'] ) );

// Tắt tự tạo dòng cha -> dòng chi bị bỏ và báo mồ côi
VHCP_Sheet::nap_ca_file( $SID, array( 'thu' => false, 'taoCha' => true, 'replace' => true ) );
$GLOBALS['VHCP_HTTP']['gid=222'] = "Mã chuyến,Nội dung,Thực chi,Ngày\nBP_khong_tao,Ăn uống,500000,01/08/2026\n";
$khong = VHCP_Sheet::nap_ca_file( $SID, array( 'thu' => false, 'taoCha' => false ) );
$mocoi = array();
foreach ( $khong['baoCao'] as $b ) { if ( ! empty( $b['moCoi'] ) ) { $mocoi = array_merge( $mocoi, (array) $b['moCoi'] ); } }
t( 'tắt tự tạo thì báo dòng mồ côi', in_array( 'BP_khong_tao', $mocoi, true ) );

// Gõ tay tên tab: bỏ hẳn bước đọc danh sách (đường dùng khi Google đổi giao diện)
$GLOBALS['VHCP_HTTP'] = array(
	'/htmlview' => array( 'code' => 200, 'body' => '<html>không có tên tab nào</html>' ),
	'/edit'     => array( 'code' => 200, 'body' => '<html>cũng không có</html>' ),
	'sheet=VH_Index' => "Mã đơn,Kỳ,Trạng thái,Người tạo\nVH_taytab,10/2026,Đang làm,Admin\n",
);
$tu_dong = VHCP_Sheet::nap_ca_file( $SID, array( 'thu' => true ) );
t( 'đọc tự động tắc thì báo lỗi có hướng xử lý', empty( $tu_dong['success'] ) && strpos( $tu_dong['error'], 'gõ tay' ) !== false );

$tay = VHCP_Sheet::nap_ca_file( $SID, array( 'thu' => false, 'tabs' => array( 'VH_Index' ) ) );
t( 'gõ tay tên tab thì nạp được', ! empty( $tay['success'] ) );
teq( 'nạp đúng 1 dòng', 1, $tay['tong'] );
t( 'báo rõ lấy danh sách tab bằng cách nào', strpos( $tay['cach'], 'gõ tay' ) === 0 );
t( 'đơn nạp bằng tên tab gõ tay đã vào', ! empty( VHCP_Don::get_don( 'VH_taytab', false )['success'] ) );

// Đọc thẳng file .xlsx: lấy GIÁ TRỊ GỐC mọi ô, không qua chỗ Google đoán kiểu cột
$xlsx = file_get_contents( __DIR__ . '/fixtures/nha-ma-ba-ria.xlsx' );
$GLOBALS['VHCP_HTTP'] = array(
	'format=xlsx' => array( 'code' => 200, 'body' => $xlsx ),
	// gviz (tải theo TÊN) xóa trắng ô tiêu đề của cột số — đường này KHÔNG được dùng nữa
	'gviz'        => "Nội dung hạng mục,,,,,,Thuộc hạng mục lớn\nVật tư Khánh Thảo,,,,,,\n",
);
$SID_X = '2XlsxEfGhIjKlMnOpQrStUvWxYz0123456789';
$wb = VHCP_Sheet::tai_workbook( $SID_X );
t( 'đọc được workbook .xlsx', isset( $wb['DA NHÀ MA BÀ RỊA'] ) );
$rows_wb = $wb['DA NHÀ MA BÀ RỊA'];
// 40 dòng đuôi chỉ có định dạng / công thức trả rỗng -> phải bị cắt, không thành 46 dòng
teq( 'cắt đuôi rỗng, còn đúng 7 dòng', 7, count( $rows_wb ) );
teq( 'banner ở dòng 1', '🏗 SETUP LẮP ĐẶT: NHÀ MA BÀ RỊA', (string) $rows_wb[0][0] );
teq( 'ô lỗi #REF! thành trống', '', (string) $rows_wb[1][4] );
// Ô rỗng dạng thẻ tự đóng <c r="A2"/> KHÔNG được ăn giá trị của ô kế tiếp
teq( 'ô rỗng tự đóng vẫn là rỗng', '', (string) $rows_wb[1][0] );
teq( 'và số vẫn nằm đúng cột C', '760127194', (string) $rows_wb[1][2] );
teq( 'tiêu đề cột số ở dòng 4 KHÔNG bị mất', 'Chi phí thực tế', (string) $rows_wb[3][2] );
teq( 'số đọc ra giá trị gốc', '825000.0000000001', (string) $rows_wb[5][2] );

$k_wb = VHCP_Nap::khop( 'sochi', $rows_wb );
t( 'nhờ vậy khớp được cột tiền', isset( $k_wb['hd']['so_tien'] ) );
t( 'và cột dự toán', isset( $k_wb['hd']['du_toan'] ) );
teq( 'chỉ còn 3 dòng dữ liệu thật (dòng trắng bị bỏ)', 3, count( $k_wb['rows'] ) );

// Số thô của .xlsx có đuôi float: bỏ dấu chấm là thành số triệu tỉ. Đọc phải ra số thật.
teq( 'số thô .xlsx không bị thổi phồng', 825000, round( VHCP_Util::doc_so( '825000.0000000001' ) ) );
teq( 'phần trăm 0,04 vẫn là 0,04', 0.04, VHCP_Util::doc_so( '0.04' ) );
teq( 'số nhóm nghìn kiểu VN vẫn đọc đúng', 1234567, VHCP_Util::doc_so( '1.234.567' ) );
teq( 'kiểu VN có phần thập phân', 1234567.5, VHCP_Util::doc_so( '1.234.567,5' ) );
teq( 'kiểu Mỹ vẫn đọc đúng', 1234567.5, VHCP_Util::doc_so( '1,234,567.5' ) );
teq( 'ô trống -> null', null, VHCP_Util::doc_so( '' ) );
teq( 'ô rác -> null', null, VHCP_Util::doc_so( '#REF!' ) );
teq( 'o_so lấy được số có đuôi float', '825000.0000000001', VHCP_Nap::o_so( $k_wb['rows'][1], $k_wb, 'so_tien' ) );

// SỐ DẠNG KHOA HỌC của .xlsx: bỏ chữ E là 564.538.680đ thành 5,65đ — đúng lỗi đã gặp
teq( 'số khoa học 5.6453868E8', 564538680, VHCP_Util::doc_so( '5.6453868E8' ) );
teq( 'số khoa học 1.08E7', 10800000, VHCP_Util::doc_so( '1.08E7' ) );
teq( 'số khoa học có dấu âm', -2500, VHCP_Util::doc_so( '-2.5E3' ) );
teq( 'số khoa học dùng dấu phẩy thập phân', 564538680, VHCP_Util::doc_so( '5,6453868E8' ) );
teq( 'đọc từ workbook cũng đúng', 549256680, round( VHCP_Util::doc_so( VHCP_Nap::o_so( $k_wb['rows'][2], $k_wb, 'so_tien' ) ) ) );

$r_wb = VHCP_Sheet::nap_ca_file( $SID_X, array( 'thu' => true, 'tabs' => array( 'DA NHÀ MA BÀ RỊA' ) ) );
teq( 'báo rõ đọc bằng file .xlsx', 'file .xlsx', $r_wb['cach'] );
$bc_wb = $r_wb['baoCao'][0];
t( 'dòng đọc: có so_tien', isset( $bc_wb['khopVoi']['so_tien'] ) );
// 825.000 + 549.256.680 (số dạng khoa học 5.4925668E8) = 550.081.680
t( 'tổng tiền tính trước cộng đủ cả số dạng khoa học', strpos( $bc_wb['ketQua'], '550.081.680' ) !== false );
// Dự toán chỉ điền ở dòng hạng mục lớn -> tổng đúng 4.800.000, không cảnh báo cộng hai lần
t( 'in trước cả TỔNG DỰ TOÁN', strpos( $bc_wb['ketQua'], 'TỔNG DỰ TOÁN 4.800.000đ' ) !== false );

// Dự toán điền ở CẢ dòng cha LẪN dòng con -> phải cảnh báo cộng hai lần, đừng nạp mù
$rows_2c = array(
	array( 'Nội dung hạng mục', 'Chi phí dự toán', 'Chi phí thực tế', 'Thuộc hạng mục lớn' ),
	array( 'Nhân Công', '10.000.000', '9.000.000', '' ),
	array( 'Thợ điện', '4.000.000', '4.000.000', 'Nhân Công' ),
	array( 'Thợ hàn', '6.000.000', '5.000.000', 'Nhân Công' ),
);
$GLOBALS['VHCP_HTTP'] = array( 'format=xlsx' => array( 'code' => 500, 'body' => '' ) );
$k_2c = VHCP_Nap::khop( 'sochi', $rows_2c );
$hm_2c = array(); $dtc = 0; $dtn = 0;
foreach ( $k_2c['rows'] as $rr ) {
	$h = VHCP_Nap::o( $rr, $k_2c['hd'], 'hang_muc' );
	if ( $h !== '' ) { $hm_2c[ mb_strtolower( $h ) ] = 1; }
}
foreach ( $k_2c['rows'] as $rr ) {
	$nd0 = VHCP_Nap::o( $rr, $k_2c['hd'], 'noi_dung' );
	$d   = VHCP_Util::num( VHCP_Util::doc_so( VHCP_Nap::o_so( $rr, $k_2c, 'du_toan' ) ) );
	if ( isset( $hm_2c[ mb_strtolower( $nd0 ) ] ) ) { $dtc += $d; } else { $dtn += $d; }
}
teq( 'dự toán dòng cha đọc đúng', 10000000, $dtc );
teq( 'dự toán dòng con đọc đúng', 10000000, $dtn );
t( 'nhận ra thế là cộng hai lần', $dtc > 0 && $dtn > 0 );
t( 'không cảnh báo tải theo tên nữa', empty( $bc_wb['canhBao'] ) );

// Nạp thật từ workbook: số phải vào đúng
$r_wb2 = VHCP_Sheet::nap_ca_file( $SID_X, array( 'thu' => false, 'replace' => true, 'tabs' => array( 'DA NHÀ MA BÀ RỊA' ) ) );
teq( 'nạp 3 dòng', 3, $r_wb2['tong'] );
$sc_wb = VHCP_SoChi::list_chi( array( 'maDuAn' => 'NHÀ MA BÀ RỊA' ) );
teq( 'tổng vào sổ chi phí đúng', 550081680, round( VHCP_Util::num( $sc_wb['tong'] ) ) );
$hom_wb = null;
foreach ( $sc_wb['items'] as $x ) { if ( (string) $x['noiDung'] === 'Tủ Điện 24 tép' ) { $hom_wb = $x; } }
teq( 'dòng con mang đúng số tiền', 825000, VHCP_Util::num( $hom_wb['soTien'] ) );
teq( 'và đúng số lượng', 1, VHCP_Util::num( $hom_wb['soLuong'] ) );
$vt_wb = null;
foreach ( $sc_wb['items'] as $x ) { if ( (string) $x['noiDung'] === 'Vật tư Khánh Thảo' ) { $vt_wb = $x; } }
teq( 'dòng tổng hợp về 0 nhưng giữ dự toán', 4800000, VHCP_Util::num( $vt_wb['duToan'] ) );
$GLOBALS['VHCP_HTTP'] = array();

// NHIỀU TAB VÀO CÙNG MỘT BẢNG: "Xoá dữ liệu cũ" chỉ được xoá MỘT LẦN cho cả lượt nạp.
// Trước đây mỗi tab đều xoá nên tab sau xoá sạch tab trước, chỉ tab cuối còn lại — màn
// dự án hiện thiếu tiền mà không có gì báo.
$SID_M = '3MotLanXoaAbCdEfGhIjKlMnOpQrStUvWx';
$GLOBALS['VHCP_HTTP'] = array(
	'/htmlview' => '{"name":"DA GIAN M","gid":"901"} {"name":"DA GIAN M (2)","gid":"902"}',
	'gid=901'   => "Nội dung hạng mục,Chi phí thực tế,Số lượng,Thuộc hạng mục lớn\nPhần một,1.000.000,1,\n",
	'gid=902'   => "Nội dung hạng mục,Chi phí thực tế,Số lượng,Thuộc hạng mục lớn\nPhần hai,2.000.000,1,\n",
);
$r_2tab = VHCP_Sheet::nap_ca_file( $SID_M, array( 'thu' => false, 'replace' => true ) );
teq( 'nạp cả 2 tab', 2, (int) $r_2tab['tong'] );
$xoa_bao = 0;
foreach ( (array) $r_2tab['baoCao'] as $b ) { if ( ! empty( $b['xoaTruoc'] ) ) { $xoa_bao++; } }
teq( 'chỉ báo xoá 1 lần', 1, $xoa_bao );
$sc_m = VHCP_SoChi::list_chi( array( 'maDuAn' => 'GIAN M' ) );
teq( 'tab đầu KHÔNG bị tab sau xoá', 3000000, VHCP_Util::num( $sc_m['tong'] ) );
// Tab nhân bản "(2)" là cùng một công trình -> cùng một mã dự án, không tách làm hai
$gop = 0;
foreach ( (array) $r_2tab['baoCao'] as $b ) { if ( ! empty( $b['gopVao'] ) ) { $gop++; } }
teq( 'báo đã gộp tab nhân bản', 1, $gop );
teq( 'không sinh ra dự án "GIAN M (2)" riêng', 0, count( VHCP_SoChi::theo_du_an( 'GIAN M (2)' ) ) );
$tg_m = VHCP_SoChi::tong_theo_du_an();
$k_m  = mb_strtolower( 'GIAN M' );
teq( 'màn dự án cộng đủ tiền cả 2 tab', 3000000, VHCP_Util::num( $tg_m[ $k_m ]['tien'] ) );
teq( 'và đếm đủ 2 dòng', 2, (int) $tg_m[ $k_m ]['n'] );

$GLOBALS['VHCP_HTTP'] = array();

// GÕ TAY THIẾU CHỮ "DA": tab thật là "DA NHÀ MA BÀ RỊA", gõ "NHÀ MA BÀ RỊA" vẫn phải ra
$GLOBALS['VHCP_HTTP'] = array( 'format=xlsx' => array( 'code' => 200, 'body' => $xlsx ) );
$r_thieu = VHCP_Sheet::nap_ca_file( $SID_X, array( 'thu' => true, 'tabs' => array( 'NHÀ MA BÀ RỊA' ) ) );
t( 'gõ thiếu chữ DA vẫn dò ra tab', ! empty( $r_thieu['success'] ) );
$bc_thieu = $r_thieu['baoCao'][0];
teq( 'và dùng đúng tên tab thật', 'DA NHÀ MA BÀ RỊA', $bc_thieu['tab'] );
t( 'không rơi xuống đường tải theo tên', empty( $bc_thieu['canhBao'] ) );
t( 'nên vẫn đọc ra tiền', strpos( $bc_thieu['ketQua'], '550.081.680' ) !== false );

// Gõ tên tab không có thật: báo thẳng kèm danh sách tab, đừng nạp 0 dòng rồi im
$r_sai = VHCP_Sheet::nap_ca_file( $SID_X, array( 'thu' => true, 'tabs' => array( 'TAB KHONG CO' ) ) );
t( 'tên tab sai thì báo lỗi rõ', empty( $r_sai['success'] ) );
t( 'và liệt kê tên tab có thật', strpos( (string) $r_sai['error'], 'DA NHÀ MA BÀ RỊA' ) !== false );
$GLOBALS['VHCP_HTTP'] = array();

// Gõ tay tên tab thì VẪN phải tải bằng gid — tải theo tên (gviz) làm cột số mất tiêu đề
$GLOBALS['VHCP_HTTP'] = array(
	'/htmlview' => '{"name":"DA GIAN X","gid":"555"}',
	// gviz (tải theo TÊN): Google đoán kiểu cột nên ô tiêu đề của cột số trả về RỖNG
	'sheet=DA%20GIAN%20X' => "Nội dung hạng mục,,,Thuộc hạng mục lớn\nMón A,,,\n",
	// export theo gid: nguyên bản, có đủ tiêu đề
	'gid=555' => "Nội dung hạng mục,Chi phí thực tế,Số lượng,Thuộc hạng mục lớn\nMón A,700.000,1,\n",
);
$gid_tay = VHCP_Sheet::nap_ca_file( $SID, array( 'thu' => true, 'tabs' => array( 'DA GIAN X' ) ) );
t( 'gõ tay tên tab vẫn dò được gid', strpos( $gid_tay['cach'], 'có gid cho 1/1' ) !== false );
$bc_gid = $gid_tay['baoCao'][0];
t( 'nhờ gid nên đọc được cột tiền', isset( $bc_gid['khopVoi']['so_tien'] ) );
t( 'tổng tiền tính trước ra đúng', strpos( $bc_gid['ketQua'], '700.000' ) !== false );
t( 'không còn cảnh báo tải theo tên', empty( $bc_gid['canhBao'] ) );

// Không dò được gid -> phải cảnh báo, vì cột số có thể mất tiêu đề
$GLOBALS['VHCP_HTTP'] = array(
	'/htmlview' => '<html>trống</html>',
	'/edit'     => '<html>trống</html>',
	'sheet=DA%20GIAN%20Y' => "Nội dung hạng mục,,,Thuộc hạng mục lớn\nMón B,,,\n",
);
$khong_gid = VHCP_Sheet::nap_ca_file( $SID, array( 'thu' => true, 'tabs' => array( 'DA GIAN Y' ) ) );
t( 'không dò được gid thì nói rõ', strpos( $khong_gid['cach'], 'KHÔNG dò được gid' ) !== false );
t( 'và cảnh báo cột số có thể mất tiêu đề', ! empty( $khong_gid['baoCao'][0]['canhBao'] ) );

// Đọc danh sách tab từ trang /edit khi htmlview tắc
$GLOBALS['VHCP_HTTP'] = array(
	'/htmlview' => array( 'code' => 200, 'body' => '<html>trống</html>' ),
	'/edit'     => array( 'code' => 200, 'body' => 'x {"gid":"777","name":"VH_Index"} y' ),
	'gid=777'   => "Mã đơn,Kỳ,Trạng thái\nVH_tu_edit,11/2026,Đang làm\n",
);
$q_edit = VHCP_Sheet::nap_ca_file( $SID, array( 'thu' => true ) );
t( 'đọc được danh sách tab từ trang edit', ! empty( $q_edit['success'] ) );
teq( 'ghi lại đọc bằng đường nào', 'edit', $q_edit['cach'] );

// Nạp cả bảng tính kiểu cũ: tab CH_* và DonHang nhận theo tên tab, cấu hình vào trước
$GLOBALS['VHCP_HTTP'] = array(
	'/htmlview' => '{"name":"DonHang","gid":"11"} {"name":"CH_CoSo","gid":"22"} {"name":"VH_Gom","gid":"33"}',
	'gid=11'    => "Mã đơn,Kỳ,Người lập,Ngày tạo,Trạng thái\nVH_ten_tab,12/2026,Admin,01/12/2026,Đang làm\n",
	'gid=22'    => "Cơ sở,Mã đơn vị,Phân loại lớn,Tên MISA\nTÀU MỚI,TAU_M,FZ MN,Tàu mới\n",
	'gid=33'    => "cột lạ,thứ hai\n1,2\n",
);
$ten_tab = VHCP_Sheet::nap_ca_file( $SID, array( 'thu' => false ) );
t( 'nạp theo tên tab chạy được', ! empty( $ten_tab['success'] ) );
$thu_tu = array(); $nhan_nho = array();
foreach ( $ten_tab['baoCao'] as $b ) {
	$thu_tu[] = $b['tab'];
	if ( isset( $b['cachNhan'] ) ) { $nhan_nho[ $b['tab'] ] = $b['cachNhan']; }
}
teq( 'cấu hình nạp trước đơn hàng', 0, array_search( 'CH_CoSo', $thu_tu, true ) );
teq( 'nhận DonHang nhờ tên tab', 'tên tab', $nhan_nho['DonHang'] );
t( 'cơ sở mới từ CH_CoSo đã vào cấu hình', VHCP_Cfg::pll_of( 'TÀU MỚI' ) === 'FZ MN' );
t( 'đơn nạp bằng bộ nạp theo vị trí đã vào', ! empty( VHCP_Don::get_don( 'VH_ten_tab', false )['success'] ) );
$bo_gom = '';
foreach ( $ten_tab['baoCao'] as $b ) { if ( $b['tab'] === 'VH_Gom' ) { $bo_gom = $b['ketQua']; } }
t( 'tab lạ bị bỏ và nói rõ vì sao', strpos( $bo_gom, 'bỏ qua' ) === 0 );
$co_dong_dau = false;
foreach ( $ten_tab['baoCao'] as $b ) { if ( $b['tab'] === 'VH_Gom' && ! empty( $b['dongDau'] ) ) { $co_dong_dau = true; } }
t( 'tab bỏ qua có in dòng đầu để soi', $co_dong_dau );

// Bảng tính chưa chia sẻ -> nói rõ, không nạp nửa vời
$GLOBALS['VHCP_HTTP'] = array( '/htmlview' => array( 'code' => 200, 'body' => '<html><a href="https://accounts.google.com/signin">Sign in</a></html>' ) );
$chua = VHCP_Sheet::nap_ca_file( $SID );
t( 'chưa chia sẻ thì báo rõ', empty( $chua['success'] ) && strpos( $chua['error'], 'chưa cho xem bằng link' ) !== false );
$GLOBALS['VHCP_HTTP'] = array();

// ------------------------------- 35. CỔNG DỰ PHÒNG admin-ajax.php dùng chung bộ xử lý
// Tab dự án của app cũ: banner ở dòng 1, dòng tổng ở dòng 2, TIÊU ĐỀ Ở DÒNG 4
$da_tab = "🏗 SETUP LẮP ĐẶT: NHÀ MA BÀ RỊA · Tạo 28/07/2026,,,,,,,,,,,,\n"
	. "TRẠNG THÁI: ĐANG LÀM,TỔNG DỰ TOÁN,760.127.194,TỔNG THỰC,#ERROR!,CHÊNH LỆCH,#ERROR!,PHÁT SINH,,#ERROR!,,,\n"
	. ",,,,,,,,,,,,\n"
	. "Nội dung hạng mục,Chi phí dự toán,Chi phí thực tế,Số lượng,Đơn giá,Thành tiền,VAT,Ảnh chi phí,Bộ phận / Gian,Ghi chú,Thuộc hạng mục lớn,Hình thức chi,Hồ sơ\n"
	. "Vật tư Khánh Thảo,34,33.534.000,1,33.534.000,33.534.000,Có VAT,,FunZone,VAT 4%,,,\n"
	. "Tủ Điện 24 tép,0,825.000,1,825.000,825.000,Có VAT,,,,Vật tư Khánh Thảo,,\n";
$rows_da = VHCP_Import::parse( $da_tab );
$k_da    = VHCP_Nap::khop( 'da_line', $rows_da );
teq( 'tìm được dòng tiêu đề ở dòng 4', 4, isset( $k_da['dongTieuDe'] ) ? $k_da['dongTieuDe'] : 0 );
teq( 'còn đúng 2 dòng dữ liệu', 2, count( $k_da['rows'] ) );
t( 'khớp được cột "Nội dung hạng mục"', isset( $k_da['hd']['noi_dung'] ) );
t( 'khớp được cột "Chi phí dự toán"', isset( $k_da['hd']['du_toan'] ) );
t( 'khớp được cột "Chi phí thực tế"', isset( $k_da['hd']['thuc_te'] ) );
t( 'khớp được cột "Thuộc hạng mục lớn"', isset( $k_da['hd']['cap_cha'] ) );
t( 'khớp được cột "Ảnh chi phí"', isset( $k_da['hd']['anh'] ) );

$da_moi = VHCP_DuAn::create_du_an( 'Setup lắp đặt', 'NHÀ MA BÀ RỊA', 'Admin' );
$r_da   = VHCP_Import::run( 'TD_DALine', $da_tab, array( 'ma' => $da_moi['maDA'] ) );
teq( 'nạp 2 dòng hạng mục', 2, $r_da['inserted'] );
$da_xem = VHCP_DuAn::get_du_an( $da_moi['maDA'] );
$dong1  = $da_xem['lines'][0];
teq( 'nội dung vào đúng cột', 'Vật tư Khánh Thảo', (string) $dong1['noiDung'] );
teq( 'số kiểu Việt "33.534.000" đọc đúng', 33534000, VHCP_Util::num( $dong1['thucTe'] ) );
teq( 'gian vào đúng cột', 'FunZone', (string) $dong1['gian'] );
teq( 'hạng mục cha của dòng 2', 'Vật tư Khánh Thảo', (string) $da_xem['lines'][1]['capCha'] );

// ------------------------------- 36. DỰ ÁN LÀ "CÁC DÒNG CHI CÙNG MÃ DỰ ÁN"
VHCP_Cfg::save_config( array(
	'coso' => array(
		array( 'ten' => 'FUNFEST SC VIVO', 'maDonVi' => 'FF_VIVO', 'phanLoaiLon' => 'FZ MN', 'tenMisa' => '' ),
	),
	'loaiChiPhi' => array(
		array( 'ten' => 'Nhân Công', 'tkNo' => '', 'tkCo' => '', 'maDt' => '', 'boPhan' => '', 'note' => '', 'tenMisa' => '', 'loaiTt' => '' ),
		array( 'ten' => 'Vật tư', 'tkNo' => '', 'tkCo' => '', 'maDt' => '', 'boPhan' => '', 'note' => '', 'tenMisa' => '', 'loaiTt' => '' ),
	),
	'tkNoMatrix' => array(
		array( 'nhom' => 'Nhân Công', 'pll' => 'FZ MN', 'tkNo' => '64121' ),
		array( 'nhom' => 'Vật tư',    'pll' => 'FZ MN', 'tkNo' => '64125' ),
	),
) );

// Đúng bố cục tab "DA FUNFEST SC VIVO": banner dòng 1, dòng tổng dòng 2, tiêu đề dòng 4
$da_vivo = "🏗 THÁO DỠ: FUNFEST SC VIVO · Tạo 02/08/2026,,,,,,,,,,,,\n"
	. "TRẠNG THÁI: ĐANG LÀM,TỔNG DỰ TOÁN,12.000.000,TỔNG THỰC,#ERROR!,CHÊNH LỆCH,#ERROR!,PHÁT SINH,,#ERROR!,,,\n"
	. ",,,,,,,,,,,,\n"
	. "Nội dung hạng mục,Chi phí dự toán,Chi phí thực tế,Số lượng,Đơn giá,Thành tiền,VAT,Ảnh chi phí,Bộ phận / Gian,Ghi chú,Thuộc hạng mục lớn,Hình thức chi,Hồ sơ\n"
	. "Nhân Công,7.200.000,12.000.000,14,1.200.000,16.800.000,Ko VAT,,Thầu,,,,\n"
	. "Dọn kho ngày 29/7,0,5.400.000,6,900.000,5.400.000,Ko VAT,https://drive.google.com/x,,Dọn chỗ cho Hàng về,Nhân Công,,\n"
	. "Vật tư Khánh Thảo,4.800.000,2.405.000,1,2.405.000,2.405.000,Có VAT,,Kỹ Thuật,VAT 4%,,,\n"
	. "Màn co 3.5kg,0,800.000,5,160.000,800.000,Có VAT,https://drive.google.com/y,,,Vật tư Khánh Thảo,,\n";

$k_vivo = VHCP_Nap::khop( 'sochi', VHCP_Import::parse( $da_vivo ) );
t( 'tab dự án khớp được từ điển sổ chi phí', empty( $k_vivo['loi'] ) );
t( 'số tiền lấy từ cột "Chi phí thực tế"', isset( $k_vivo['hd']['so_tien'] ) );
t( 'lấy được dự toán', isset( $k_vivo['hd']['du_toan'] ) );
t( 'lấy được hạng mục lớn', isset( $k_vivo['hd']['hang_muc'] ) );
t( 'KHÔNG lấy "Bộ phận / Gian" làm cơ sở', ! isset( $k_vivo['hd']['coso'] ) );
t( 'nhưng vẫn đọc được cột bộ phận', isset( $k_vivo['hd']['bo_phan'] ) );

// Cột "Bộ phận / Gian" của tab dự án ghi tổ/thầu, KHÔNG phải cơ sở -> cơ sở truyền riêng
$r_vivo = VHCP_Import::run( 'TD_SoChi', $da_vivo, array( 'replace' => true, 'ma' => 'FUNFEST SC VIVO', 'coso' => 'FUNFEST SC VIVO' ) );
teq( 'nạp 4 dòng vào sổ chi phí', 4, $r_vivo['inserted'] );

$sc = VHCP_SoChi::list_chi( array( 'maDuAn' => 'FUNFEST SC VIVO' ) );
teq( 'lọc theo mã dự án ra đúng 4 dòng', 4, $sc['soDong'] );
// Dòng "Nhân Công" và "Vật tư Khánh Thảo" là DÒNG TỔNG HỢP của các dòng con bên dưới,
// nạp cả hai là đếm hai lần -> chúng phải về 0, tổng chỉ còn 5.400.000 + 800.000
teq( 'tổng không đếm hai lần dòng tổng hợp', 6200000, VHCP_Util::num( $sc['tong'] ) );
$da_tong = $sc['byDuAn'][0];
teq( 'gom theo mã dự án', 'FUNFEST SC VIVO', $da_tong['maDuAn'] );
teq( 'dự toán của dự án cộng đúng', 12000000, VHCP_Util::num( $da_tong['duToan'] ) );
t( 'mã dự án hiện trong danh sách lọc', in_array( 'FUNFEST SC VIVO', $sc['duAnList'], true ) );

$dong_nc = null;
foreach ( $sc['items'] as $x ) { if ( (string) $x['noiDung'] === 'Nhân Công' ) { $dong_nc = $x; } }
t( 'có dòng Nhân Công', $dong_nc !== null );
teq( 'mã dự án gắn vào từng dòng', 'FUNFEST SC VIVO', (string) $dong_nc['maDuAn'] );
teq( 'dòng tổng hợp bị đưa về 0 để không đếm hai lần', 0, VHCP_Util::num( $dong_nc['soTien'] ) );
teq( 'nhưng vẫn giữ dự toán của hạng mục', 7200000, VHCP_Util::num( $dong_nc['duToan'] ) );
$dong_con2 = null;
foreach ( $sc['items'] as $x ) { if ( (string) $x['noiDung'] === 'Dọn kho ngày 29/7' ) { $dong_con2 = $x; } }
teq( 'dòng con giữ nguyên số thực chi', 5400000, VHCP_Util::num( $dong_con2['soTien'] ) );
teq( 'loại chi phí suy từ hạng mục lớn', 'Nhân Công', (string) $dong_con2['loai'] );
teq( 'dòng tổng hợp lấy chính tên nó làm loại', 'Nhân Công', (string) $dong_nc['loai'] );
$dong_con = null;
foreach ( $sc['items'] as $x ) { if ( (string) $x['noiDung'] === 'Màn co 3.5kg' ) { $dong_con = $x; } }
teq( 'hạng mục lớn của dòng con', 'Vật tư Khánh Thảo', (string) $dong_con['hangMuc'] );

// Cùng loại chi phí -> ra mã theo mảng của cơ sở, dù dòng thuộc dự án
$dong_mavt = null;
foreach ( $sc['items'] as $x ) { if ( (string) $x['loai'] === 'Nhân Công' ) { $dong_mavt = $x; } }
teq( 'dòng dự án ăn mã theo mảng của cơ sở', '64121', (string) $dong_mavt['tkNo'] );
teq( 'bộ phận/gian không thành cơ sở mà vào ghi chú', 'FUNFEST SC VIVO', (string) $dong_mavt['coso'] );
t( 'giữ lại thông tin tổ/thầu trong ghi chú', strpos( (string) $dong_nc['ghiChu'], '[Thầu]' ) === 0 );

// Dòng không thuộc dự án nào thì lọc "(khong)" ra được
VHCP_SoChi::add( array( 'ngay' => $today, 'coso' => 'FUNFEST SC VIVO', 'loai' => 'Nhân Công', 'noiDung' => 'Chi lẻ', 'soTien' => 111000, 'hinhThuc' => 'Tạm ứng NV' ), 'NV A' );
$sc_khong = VHCP_SoChi::list_chi( array( 'maDuAn' => '(khong)' ) );
teq( 'lọc dòng không thuộc dự án', 1, $sc_khong['soDong'] );
teq( 'đúng dòng chi lẻ', 'Chi lẻ', (string) $sc_khong['items'][0]['noiDung'] );

// Loại chi phí suy ra từ dữ liệu cũ phải VÀO DANH MỤC, không thì không có cách nào khai mã
$dm_ten = array();
foreach ( VHCP_Cfg::cfg_static()['loaiChiPhi'] as $x ) { $dm_ten[ mb_strtolower( trim( $x['ten'] ) ) ] = 1; }
t( 'loại "Nhân Công" đã vào danh mục', isset( $dm_ten['nhân công'] ) );
t( 'loại "Vật tư Khánh Thảo" đã vào danh mục', isset( $dm_ten['vật tư khánh thảo'] ) );
t( 'có báo số loại vừa thêm', isset( $r_vivo['themLoai'] ) );
// Tên loại chưa từng có -> phải được thêm vào danh mục
$csv_moi = "Nội dung hạng mục,Chi phí thực tế,Thuộc hạng mục lớn\nSơn nước,500.000,Hoàn thiện mới\n";
$r_moi = VHCP_Import::run( 'TD_SoChi', $csv_moi, array( 'ma' => 'FUNFEST SC VIVO', 'coso' => 'FUNFEST SC VIVO' ) );
teq( 'thêm loại chưa từng có vào danh mục', 1, (int) $r_moi['themLoai'] );
t( 'loại mới có mặt trong danh mục', VHCP_Cfg::loai_tk( 'Hoàn thiện mới' ) !== null );

// Nạp lại lần nữa không sinh loại trùng
$r_lai = VHCP_Import::run( 'TD_SoChi', $da_vivo, array( 'replace' => true, 'ma' => 'FUNFEST SC VIVO', 'coso' => 'FUNFEST SC VIVO' ) );
teq( 'nạp lại không thêm loại trùng', 0, (int) $r_lai['themLoai'] );
$dem_nc = 0;
foreach ( VHCP_Cfg::cfg_static()['loaiChiPhi'] as $x ) { if ( mb_strtolower( trim( $x['ten'] ) ) === 'nhân công' ) { $dem_nc++; } }
teq( 'danh mục chỉ có 1 dòng "Nhân Công"', 1, $dem_nc );

// Khai mã cho loại vừa sinh -> bấm gán mã là các dòng cũ ăn mã ngay
VHCP_Cfg::khai_cho_coso( array( 'ten' => 'Nhân Công', 'tkNo' => '64121', 'cosos' => array( 'FUNFEST SC VIVO' ) ) );
$gm2 = VHCP_SoChi::gan_ma_tai_khoan( false );
$sc2 = VHCP_SoChi::list_chi( array( 'maDuAn' => 'FUNFEST SC VIVO' ) );
$ma_nc = '';
foreach ( $sc2['items'] as $x ) { if ( (string) $x['noiDung'] === 'Dọn kho ngày 29/7' ) { $ma_nc = (string) $x['tkNo']; } }
teq( 'khai mã xong bấm gán là dòng cũ ăn mã', '64121', $ma_nc );

// Dò cơ sở từ tên tab dự án: khớp 1 thì lấy, khớp nhiều hoặc không khớp thì để trống
VHCP_Cfg::save_config( array( 'coso' => array(
	array( 'ten' => 'EVENT FARM NHA TRANG', 'maDonVi' => 'EV_FNT', 'phanLoaiLon' => 'FARM MN', 'tenMisa' => '' ),
	array( 'ten' => 'FUNFEST SC VIVO', 'maDonVi' => 'FF_VIVO', 'phanLoaiLon' => 'FZ MN', 'tenMisa' => '' ),
	array( 'ten' => 'SNOW NHÀ TUYẾT TÂN PHÚ', 'maDonVi' => 'SN_TP', 'phanLoaiLon' => 'EVENT SNOW MN', 'tenMisa' => '' ),
	array( 'ten' => 'SNOW NHÀ TUYẾT BÌNH DƯƠNG', 'maDonVi' => 'SN_BD', 'phanLoaiLon' => 'EVENT SNOW MN', 'tenMisa' => '' ),
) ) );
$ct1 = VHCP_Sheet::coso_cua_tab( 'DA FARM NHA TRANG (2)' );
teq( 'tên tab khớp đúng 1 cơ sở thì lấy', 'EVENT FARM NHA TRANG', $ct1['coso'] );
$ct2 = VHCP_Sheet::coso_cua_tab( 'DA FUNFEST SC VIVO' );
teq( 'khớp chính xác', 'FUNFEST SC VIVO', $ct2['coso'] );
$ct3 = VHCP_Sheet::coso_cua_tab( 'DA Chi phí co so (chung)' );
teq( 'không khớp cơ sở nào thì để trống', '', $ct3['coso'] );
t( 'và nói rõ vì sao', strpos( $ct3['ghiChu'], 'không tìm được' ) === 0 );

// Xuất MISA của dòng dự án: mã dự án phải nằm trong diễn giải
$xm = VHCP_SoChi::export_misa( 'all', 'chuaxuat' );
$co_ma_da = false;
foreach ( $xm['rows'] as $row ) { if ( strpos( (string) $row[4], 'FUNFEST SC VIVO' ) !== false ) { $co_ma_da = true; } }
t( 'diễn giải MISA có mã dự án', $co_ma_da );

// ------------------------------- 37. DỰ ÁN CHI TRỰC TIẾP — bỏ bước duyệt tạm ứng
$da_tt = VHCP_DuAn::create_du_an( 'Tháo dỡ', 'GIAN THỬ CHI TRỰC TIẾP', 'Admin' );
$ma_tt = (string) $da_tt['maDA'];
$xem   = VHCP_DuAn::get_du_an( $ma_tt );
teq( 'dự án mới ở trạng thái đang làm', 'Đang làm', (string) $xem['trangThai'] );
t( 'nhập được ngay, không chờ duyệt', ! empty( $xem['editable'] ) && ! empty( $xem['thiCong'] ) );
t( 'không còn trạng thái chờ duyệt', empty( $xem['pending'] ) );

// Đang làm là đóng được luôn (trước đây đòi phải "Đã duyệt")
$dong = VHCP_DuAn::close( $ma_tt );
t( 'đóng dự án ngay khi đang làm', ! empty( $dong['success'] ) );
teq( 'trạng thái sau khi đóng', 'Đã đóng', (string) VHCP_DuAn::get_du_an( $ma_tt )['trangThai'] );
t( 'đóng rồi thì khoá nhập', empty( VHCP_DuAn::get_du_an( $ma_tt )['editable'] ) );
t( 'đóng hai lần thì báo lỗi', empty( VHCP_DuAn::close( $ma_tt )['success'] ) );
t( 'mở lại được', ! empty( VHCP_DuAn::reopen( $ma_tt )['success'] ) );
t( 'mở lại thì nhập được tiếp', ! empty( VHCP_DuAn::get_du_an( $ma_tt )['editable'] ) );

// ------------------------------- 38. MÀN DỰ ÁN PHẢI CỘNG CẢ PHẦN Ở SỔ CHI PHÍ
$da_gian = VHCP_DuAn::create_du_an( 'Tháo dỡ', 'FARM NHA TRANG', 'Admin' );
$ma_gian = (string) $da_gian['maDA'];
// dòng chi nạp vào sổ chi phí, mang MÃ DỰ ÁN = tên gian (đúng như bộ đọc Sheet làm)
VHCP_SoChi::add( array( 'ngay' => $today, 'coso' => 'EVENT FARM NHA TRANG', 'loai' => 'Nhân Công',
	'noiDung' => 'Tháo dỡ đợt 1', 'soTien' => 6000000, 'duToan' => 7000000,
	'hinhThuc' => 'Tạm ứng NV', 'maDuAn' => 'FARM NHA TRANG', 'hangMuc' => 'Nhân Công' ), 'NV A' );
VHCP_SoChi::add( array( 'ngay' => $today, 'coso' => 'EVENT FARM NHA TRANG', 'loai' => 'Nhân Công',
	'noiDung' => 'Tháo dỡ đợt 2', 'soTien' => 4000000,
	'hinhThuc' => 'Tạm ứng NV', 'maDuAn' => 'FARM NHA TRANG' ), 'NV A' );

$xg = VHCP_DuAn::get_du_an( $ma_gian );
teq( 'màn dự án thấy 2 dòng từ sổ chi phí', 2, count( $xg['soChi'] ) );
teq( 'tổng phần sổ chi phí', 10000000, VHCP_Util::num( $xg['tongSoChi'] ) );
teq( 'tổng thực tế của gian không còn là 0', 10000000, VHCP_Util::num( $xg['tongThucTe'] ) );
teq( 'dự toán cộng cả phần sổ chi phí', 7000000, VHCP_Util::num( $xg['tongDuToan'] ) );

$ds = VHCP_DuAn::list_du_an();
$dong_gian = null;
foreach ( $ds['items'] as $x ) { if ( (string) $x['ten'] === 'FARM NHA TRANG' ) { $dong_gian = $x; } }
t( 'gian có trong danh sách dự án', $dong_gian !== null );
teq( 'danh sách dự án cũng cộng phần sổ chi phí', 10000000, VHCP_Util::num( $dong_gian['tongThucTe'] ) );
teq( 'đếm số dòng nằm ở sổ chi phí', 2, (int) $dong_gian['soDongSoChi'] );

// Gian ĐÃ ĐÓNG vẫn nhận được dòng chi ở sổ chi phí (sổ chi phí không theo trạng thái dự án)
VHCP_DuAn::close( $ma_gian );
$them = VHCP_SoChi::add( array( 'ngay' => $today, 'coso' => 'EVENT FARM NHA TRANG', 'loai' => 'Nhân Công',
	'noiDung' => 'Bù sau khi đóng', 'soTien' => 500000, 'hinhThuc' => 'Tạm ứng NV', 'maDuAn' => 'FARM NHA TRANG' ), 'NV A' );
t( 'gian đã đóng vẫn ghi được vào sổ chi phí', ! empty( $them['success'] ) );
teq( 'và số của gian cập nhật theo', 10500000, VHCP_Util::num( VHCP_DuAn::get_du_an( $ma_gian )['tongSoChi'] ) );

// ------------------------------- 39. TAB NÀY ĐIỀN "THÀNH TIỀN", TAB KIA "CHI PHÍ THỰC TẾ"
// Tab NHÀ MA BÀ RỊA: cột "Chi phí thực tế" để trống/0, tiền nằm ở "Thành tiền".
// Lấy cứng một cột là nạp ra 0 hết — dạng sai im lặng.
$da_bo = "🏗 SETUP LẮP ĐẶT: NHÀ MA BÀ RỊA,,,,,,,,,,,,\n"
	. "TRẠNG THÁI: ĐANG LÀM,TỔNG DỰ TOÁN,24.340.000,TỔNG THỰC,#ERROR!,,,,,,,,\n"
	. ",,,,,,,,,,,,\n"
	. "Nội dung hạng mục,Chi phí dự toán,Chi phí thực tế,Số lượng,Đơn giá,Thành tiền,VAT,Ảnh chi phí,Bộ phận / Gian,Ghi chú,Thuộc hạng mục lớn,Hình thức chi,Hồ sơ\n"
	. "Đồ Gỗ,24.340.000,,,,24.340.000,,,FunZone,Cắt CNC và Đồ Cũ,,Tạm ứng,\n"
	. "Hòm (cắt gỗ CNC theo yêu cầu),0,,2,4.000.000,8.000.000,,,,,Đồ Gỗ,,\n"
	. "Ghế chân ngựa,0,,2,400.000,800.000,,,,,Đồ Gỗ,,\n"
	. "Bàn 1.2m,0,,3,430.000,1.290.000,,,,,Đồ Gỗ,,\n";

$k_bo = VHCP_Nap::khop( 'sochi', VHCP_Import::parse( $da_bo ) );
t( 'ghi nhận NHIỀU cột cho cùng một trường tiền', isset( $k_bo['hdAll']['so_tien'] ) && count( $k_bo['hdAll']['so_tien'] ) >= 2 );
$hang_hom = $k_bo['rows'][1];
teq( 'lấy cột có số, không lấy cột rỗng', '8.000.000', VHCP_Nap::o_so( $hang_hom, $k_bo, 'so_tien' ) );

$r_bo = VHCP_Import::run( 'TD_SoChi', $da_bo, array( 'replace' => true, 'ma' => 'NHÀ MA BÀ RỊA', 'coso' => 'FUNFEST SC VIVO' ) );
teq( 'nạp 4 dòng', 4, $r_bo['inserted'] );
$sc_bo = VHCP_SoChi::list_chi( array( 'maDuAn' => 'NHÀ MA BÀ RỊA' ) );
teq( 'tổng đúng, không còn 0', 10090000, VHCP_Util::num( $sc_bo['tong'] ) );   // 8.000.000 + 800.000 + 1.290.000
$hom = null;
foreach ( $sc_bo['items'] as $x ) { if ( strpos( (string) $x['noiDung'], 'Hòm' ) === 0 ) { $hom = $x; } }
teq( 'số tiền của dòng con', 8000000, VHCP_Util::num( $hom['soTien'] ) );
teq( 'số lượng vào đúng cột', 2, VHCP_Util::num( $hom['soLuong'] ) );
teq( 'đơn giá vào đúng cột', 4000000, VHCP_Util::num( $hom['donGia'] ) );
$dogo = null;
foreach ( $sc_bo['items'] as $x ) { if ( (string) $x['noiDung'] === 'Đồ Gỗ' ) { $dogo = $x; } }
teq( 'dòng tổng hợp vẫn về 0', 0, VHCP_Util::num( $dogo['soTien'] ) );
teq( 'nhưng giữ dự toán 24.340.000', 24340000, VHCP_Util::num( $dogo['duToan'] ) );

// Trường hợp ngược lại: chỉ có "Chi phí thực tế" thì vẫn lấy đúng
$da_nguoc = "Nội dung hạng mục,Chi phí thực tế,Thành tiền\nSơn,3.500.000,\n";
$r_ng = VHCP_Import::run( 'TD_SoChi', $da_nguoc, array( 'ma' => 'X1', 'coso' => 'FUNFEST SC VIVO' ) );
teq( 'nạp được dòng chỉ có Chi phí thực tế', 1, $r_ng['inserted'] );
$sc_ng = VHCP_SoChi::list_chi( array( 'maDuAn' => 'X1' ) );
teq( 'lấy đúng cột còn lại', 3500000, VHCP_Util::num( $sc_ng['tong'] ) );

// Dòng đã xuất MISA ở hệ cũ phải vào app ở trạng thái ĐÃ XUẤT, không thì xuất trùng
$csv_dx = "Nội dung hạng mục,Chi phí thực tế,Ngày xuất MISA\n"
	. "Đã xuất rồi,1.000.000,13/08/2026\n"
	. "Chưa xuất,2.000.000,\n";
$r_dx = VHCP_Import::run( 'TD_SoChi', $csv_dx, array( 'ma' => 'DX1', 'coso' => 'FUNFEST SC VIVO' ) );
teq( 'nạp 2 dòng', 2, $r_dx['inserted'] );
$sc_dx = VHCP_SoChi::list_chi( array( 'maDuAn' => 'DX1' ) );
$da_x = null; $chua_x = null;
foreach ( $sc_dx['items'] as $x ) {
	if ( (string) $x['noiDung'] === 'Đã xuất rồi' ) { $da_x = $x; }
	if ( (string) $x['noiDung'] === 'Chưa xuất' ) { $chua_x = $x; }
}
t( 'dòng có ngày xuất MISA vào app là đã xuất', ! empty( $da_x['daXuat'] ) );
t( 'dòng không có thì vẫn là chưa xuất', empty( $chua_x['daXuat'] ) );
$xm2 = VHCP_SoChi::export_misa( 'all', 'chuaxuat' );
$co_trung = false;
foreach ( $xm2['rows'] as $row ) { if ( strpos( (string) $row[4], 'Đã xuất rồi' ) !== false ) { $co_trung = true; } }
t( 'xuất MISA lần tới KHÔNG lấy lại dòng đã xuất', ! $co_trung );

// Mã dự án CÓ DẤU phải tra được: LOWER() của SQL không hạ chữ có dấu
$da_dau = VHCP_DuAn::create_du_an( 'Setup lắp đặt', 'GIAN CÓ DẤU ĐẶC BIỆT', 'Admin' );
VHCP_SoChi::add( array( 'ngay' => $today, 'coso' => 'FUNFEST SC VIVO', 'loai' => 'Vật tư',
	'noiDung' => 'Tủ Điện 24 tép', 'soTien' => 825000, 'duToan' => 4800000,
	'hinhThuc' => 'Tạm ứng NV', 'maDuAn' => 'GIAN CÓ DẤU ĐẶC BIỆT' ), 'NV A' );
VHCP_SoChi::add( array( 'ngay' => $today, 'coso' => 'FUNFEST SC VIVO', 'loai' => 'Vật tư',
	'noiDung' => 'Màn co 3.5kg', 'soTien' => 800000,
	'hinhThuc' => 'Tạm ứng NV', 'maDuAn' => 'GIAN CÓ DẤU ĐẶC BIỆT' ), 'NV A' );
teq( 'tra được mã dự án có dấu', 2, count( VHCP_SoChi::theo_du_an( 'GIAN CÓ DẤU ĐẶC BIỆT' ) ) );
$x_dau = VHCP_DuAn::get_du_an( (string) $da_dau['maDA'] );
teq( 'màn dự án tên có dấu thấy đủ dòng', 2, count( $x_dau['soChi'] ) );
teq( 'tổng thực tế của gian tên có dấu', 1625000, VHCP_Util::num( $x_dau['tongThucTe'] ) );
teq( 'tổng dự toán của gian tên có dấu', 4800000, VHCP_Util::num( $x_dau['tongDuToan'] ) );
$ds_dau = VHCP_DuAn::list_du_an();
$dong_dau = null;
foreach ( $ds_dau['items'] as $y ) { if ( (string) $y['ten'] === 'GIAN CÓ DẤU ĐẶC BIỆT' ) { $dong_dau = $y; } }
teq( 'danh sách dự án cũng ra số cho tên có dấu', 1625000, VHCP_Util::num( $dong_dau['tongThucTe'] ) );

// Báo cáo nạp phải tự nói ra TỔNG TIỀN và số dòng ra 0đ, để biết ngay là đọc sai cột
$csv_bc = "Nội dung hạng mục,Chi phí thực tế,Thành tiền,Thuộc hạng mục lớn\n"
	. "Nhóm A,0,0,\n"
	. "Món 1,300.000,300.000,Nhóm A\n"
	. "Món 2,,,Nhóm A\n";
$r_bc = VHCP_Import::run( 'TD_SoChi', $csv_bc, array( 'ma' => 'BC1', 'coso' => 'FUNFEST SC VIVO' ) );
teq( 'báo cáo có tổng tiền', 300000, VHCP_Util::num( $r_bc['tongTien'] ) );
teq( 'đếm dòng ra 0đ (không tính dòng tổng hợp)', 1, (int) $r_bc['khongTien'] );
teq( 'đếm dòng tổng hợp', 1, (int) $r_bc['dongTong'] );

// ------------------------------- 40. XÓA SẠCH DỮ LIỆU NHƯNG GIỮ CẤU HÌNH
$so_loai_truoc = count( VHCP_Cfg::cfg_static()['loaiChiPhi'] );
$so_coso_truoc = count( VHCP_Cfg::cfg_static()['coso'] );
$so_mx_truoc   = count( VHCP_Cfg::cfg_static()['tkNoMatrix'] );
t( 'trước khi xóa: sổ chi phí có dữ liệu', VHCP_SoChi::list_chi( array() )['soDong'] > 0 );

$xoa = VHCP_DB::xoa_du_lieu();
t( 'xóa xong có báo số dòng từng bảng', is_array( $xoa ) && isset( $xoa['so_chi'] ) );
teq( 'sổ chi phí sạch', 0, VHCP_SoChi::list_chi( array() )['soDong'] );
teq( 'dự án sạch', 0, count( VHCP_DuAn::list_du_an()['items'] ) );
teq( 'đơn vận hành sạch', 0, count( VHCP_Don::list_dons() ) );
teq( 'đợt công tác sạch', 0, count( VHCP_BP::all_with_lines() ) );
teq( 'đơn marketing sạch', 0, count( VHCP_MK::all_dons() ) );

// Cấu hình phải còn nguyên — đó là phần khai tay mất công nhất
teq( 'danh mục loại chi phí còn nguyên', $so_loai_truoc, count( VHCP_Cfg::cfg_static()['loaiChiPhi'] ) );
teq( 'danh sách cơ sở còn nguyên', $so_coso_truoc, count( VHCP_Cfg::cfg_static()['coso'] ) );
teq( 'ma trận mã còn nguyên', $so_mx_truoc, count( VHCP_Cfg::cfg_static()['tkNoMatrix'] ) );
t( 'người dùng còn đăng nhập được', ! empty( VHCP_Auth::login( '2222' )['ok'] ) );

// ---------------------------------------------------------------- kết quả
echo "\n";
// ------------------------------- NẠP KHÔNG SINH LOẠI TRÙNG NGHĨA
// Bảng tính cũ ghi hạng mục là "Tháo dỡ" / "Vận hành", trong khi danh mục đã có
// "Chi phí tháo dỡ" / "Chi phí vận hành" -> danh mục thành hai dòng cùng nghĩa, nhân viên
// chọn lộn mà kế toán phải khai mã hai lần.
VHCP_Cfg::them_loai_neu_thieu( array( 'Chi phí tháo dỡ', 'Chi phí vận hành' ) );
$n_trung = VHCP_Cfg::them_loai_neu_thieu( array( 'Tháo dỡ', 'Vận hành' ) );
teq( 'không thêm loại trùng nghĩa', 0, $n_trung );
$ten_dm = array();
foreach ( VHCP_Cfg::get_config()['loaiChiPhi'] as $x ) { $ten_dm[] = $x['ten']; }
t( 'giữ "Chi phí tháo dỡ"', in_array( 'Chi phí tháo dỡ', $ten_dm, true ) );
t( 'KHÔNG sinh thêm "Tháo dỡ"', ! in_array( 'Tháo dỡ', $ten_dm, true ) );
// Tên khác nghĩa thì vẫn thêm bình thường
$n_khac = VHCP_Cfg::them_loai_neu_thieu( array( 'Chi phí khác - Event' ) );
teq( 'tên khác nghĩa vẫn thêm', 1, $n_khac );

// ------------------------------- MÃ SỐ MANG ĐUÔI ".0" TỪ BẢNG TÍNH
// Bảng tính coi mã tài khoản là SỐ nên xuất ra "64196.0", "6329.0". Mã đó không khớp hệ
// thống tài khoản và xuất MISA ra sai, mà nhìn bảng cấu hình vẫn thấy mã nằm đó.
VHCP_Cfg::save_config( array(
	'coso' => array(
		array( 'ten' => 'GIAN ĐUÔI CHẤM', 'maDonVi' => '12345.0', 'phanLoaiLon' => 'FZ MN', 'tenMisa' => 'Gian đuôi chấm' ),
	),
	'loaiChiPhi' => array(
		array( 'ten' => 'CP Đuôi Chấm', 'tkNo' => '64125.0', 'tkCo' => '331.0', 'maDt' => 'NV9.0', 'boPhan' => '', 'tenMisa' => '' ),
	),
	'tkNoMatrix' => array(
		array( 'nhom' => 'CP Đuôi Chấm', 'pll' => 'FZ MN', 'tkNo' => '64196.0' ),
	),
) );
$cf_dc = VHCP_Cfg::get_config();
$cs_dc = null; $lo_dc = null;
foreach ( $cf_dc['coso'] as $x ) { if ( $x['ten'] === 'GIAN ĐUÔI CHẤM' ) { $cs_dc = $x; } }
foreach ( $cf_dc['loaiChiPhi'] as $x ) { if ( $x['ten'] === 'CP Đuôi Chấm' ) { $lo_dc = $x; } }
teq( 'mã đơn vị bỏ đuôi .0', '12345', $cs_dc['maDonVi'] );
teq( 'TK Nợ của loại bỏ đuôi .0', '64125', $lo_dc['tkNo'] );
teq( 'TK Có bỏ đuôi .0', '331', $lo_dc['tkCo'] );
teq( 'mã đối tượng bỏ đuôi .0', 'NV9', $lo_dc['maDt'] );
$mx_dc = '';
foreach ( $cf_dc['tkNoMatrix'] as $x ) { if ( $x['nhom'] === 'CP Đuôi Chấm' ) { $mx_dc = $x['tkNo']; } }
teq( 'mã trong ma trận bỏ đuôi .0', '64196', $mx_dc );
teq( 'và resolve ra mã sạch', '64196', VHCP_Cfg::resolve_tk( 'CP Đuôi Chấm', '', array(), 'GIAN ĐUÔI CHẤM' )['tk_no'] );

// Dòng ĐÃ LƯU từ trước còn mang đuôi .0 thì lúc XUẤT MISA cũng phải ra mã sạch — không thì
// phải đi sửa tay từng dòng cũ mới xuất được.
$sc_dc = VHCP_SoChi::add( array(
	'ngay' => $today, 'coso' => 'GIAN ĐUÔI CHẤM', 'loai' => 'CP Đuôi Chấm', 'noiDung' => 'Thử đuôi chấm',
	'soTien' => 500000, 'hinhThuc' => 'Tạm ứng', 'tkNo' => '64196.0', 'tkCo' => '141.0', 'maDt' => 'NV9.0',
), 'Test' );
t( 'thêm được dòng mang mã đuôi .0', ! empty( $sc_dc['success'] ) );
$ex_dc = VHCP_SoChi::export_misa( array() );
$hang = null;
foreach ( (array) $ex_dc['rows'] as $rw ) { if ( strpos( (string) $rw[4], 'Thử đuôi chấm' ) !== false || strpos( (string) $rw[3], 'Thử đuôi chấm' ) !== false ) { $hang = $rw; } }
if ( $hang ) {
	teq( 'xuất MISA: TK Nợ sạch đuôi', '64196', (string) $hang[5] );
	teq( 'xuất MISA: TK Có sạch đuôi', '141', (string) $hang[6] );
	teq( 'xuất MISA: mã đối tượng sạch đuôi', 'NV9', (string) $hang[8] );
	teq( 'xuất MISA: mã đơn vị sạch đuôi', '12345', (string) $hang[9] );
}

// ------------------------------- ĐÓNG CỬA GIAN HÀNG THÌ HẾT LUÂN CHUYỂN BÙ TRỪ
// Kế toán làm lệnh đóng gian là đã tất toán hết bằng tiền; còn trừ tiếp sang kỳ sau là trừ
// hai lần một khoản.
VHCP_Cfg::save_config( array( 'coso' => array(
	array( 'ten' => 'FARM PHAN THIẾT', 'maDonVi' => 'FARM_PT', 'phanLoaiLon' => 'FARM', 'tenMisa' => 'Farm Phan Thiết' ),
	array( 'ten' => 'TÀU TÂN PHÚ', 'maDonVi' => 'TAU_TP', 'phanLoaiLon' => 'TAU', 'tenMisa' => 'Tàu Tân Phú' ),
	array( 'ten' => 'GIAN SẼ ĐÓNG', 'maDonVi' => 'GD', 'phanLoaiLon' => 'FARM', 'tenMisa' => 'Gian sẽ đóng' ),
) ) );
teq( 'gian mới thì chưa đóng', '', VHCP_Cfg::coso_dong_cua( 'GIAN SẼ ĐÓNG' ) );

$d_c1 = VHCP_Don::create_don( 'T9/2026 (15/9-21/9/2026)', 'NV Gian Đóng' );
$mc1  = $d_c1['maDon'];
VHCP_Don::add_line( $mc1, array( 'coso' => 'GIAN SẼ ĐÓNG', 'ngay' => $today, 'phanLoaiTT' => 'Thanh toán cá nhân', 'nhom' => 'Chi phí cơ sở', 'noiDung' => 'Mua đồ', 'soLuong' => 1, 'donGia' => 1000000, 'thanhTien' => 1000000 ) );
VHCP_Don::gui_duyet_tam_ung( $mc1 );
VHCP_Don::duyet_tam_ung( $mc1, 'Trần Quản Lý', '' );
VHCP_Don::cap_tam_ung( $mc1, 'Lê Kế Toán', 'Tiền mặt' );
// còn dư: tạm ứng 1.000.000, thực mua 600.000
VHCP_Don::set_line_thuc_mua( VHCP_Don::get_don( $mc1 )['lines'][0]['id'], 600000, 'Lê Kế Toán' );

$d_c2 = VHCP_Don::create_don( 'T9/2026 (22/9-28/9/2026)', 'NV Gian Đóng' );
$mc2  = $d_c2['maDon'];
teq( 'chưa đóng gian thì kỳ sau bị trừ phần dư', -400000, VHCP_Util::num( VHCP_Don::get_don( $mc2 )['don']['buTru'] ) );

$dc = VHCP_Cfg::dong_cua_coso( 'GIAN SẼ ĐÓNG', true, 'Lê Kế Toán' );
t( 'kế toán đóng được gian hàng', ! empty( $dc['success'] ), $dc );
t( 'ghi lại ngày đóng', VHCP_Cfg::coso_dong_cua( 'GIAN SẼ ĐÓNG' ) !== '' );
t( 'tất toán luôn đơn còn treo của gian', (int) $dc['soDonTatToan'] >= 1, $dc );
$g_c2 = VHCP_Don::get_don( $mc2 );
teq( 'đóng gian rồi thì bù trừ về 0', 0, VHCP_Util::num( $g_c2['don']['buTru'] ) );
t( 'và nói rõ đơn của gian đã đóng bị loại khỏi tổng',
	strpos( (string) $g_c2['don']['buTruAuto']['lyDo'], 'GIAN SẼ ĐÓNG đóng' ) !== false
	|| strpos( (string) $g_c2['don']['buTruAuto']['lyDo'], 'đã tất toán' ) !== false,
	$g_c2['don']['buTruAuto'] );

$them = VHCP_Don::add_line( $mc2, array( 'coso' => 'GIAN SẼ ĐÓNG', 'ngay' => $today, 'phanLoaiTT' => 'Thanh toán cá nhân', 'nhom' => 'Chi phí cơ sở', 'noiDung' => 'Chi thêm', 'soLuong' => 1, 'donGia' => 1, 'thanhTien' => 1 ) );
t( 'gian đã đóng thì không nhận chi mới', empty( $them['success'] ) );
t( 'câu báo nêu ngày đóng', strpos( (string) $them['error'], 'đã đóng cửa ngày' ) !== false, $them );

// getBootstrap gửi kèm danh sách gian đã đóng để giao diện bỏ khỏi ô chọn
$bs_dong = VHCP_Don::get_bootstrap();
t( 'bootstrap báo gian đã đóng', in_array( 'GIAN SẼ ĐÓNG', (array) $bs_dong['cosoDong'], true ), $bs_dong['cosoDong'] );
t( 'gian còn mở không nằm trong đó', ! in_array( 'TÀU TÂN PHÚ', (array) $bs_dong['cosoDong'], true ) );

t( 'mở lại gian được', ! empty( VHCP_Cfg::dong_cua_coso( 'GIAN SẼ ĐÓNG', false, 'Lê Kế Toán' )['success'] ) );
teq( 'mở lại thì xóa ngày đóng', '', VHCP_Cfg::coso_dong_cua( 'GIAN SẼ ĐÓNG' ) );
VHCP_Don::delete_don_admin( $mc1 );
VHCP_Don::delete_don_admin( $mc2 );

// ------------------------------- XIN TẠM ỨNG CHỈ CẦN DỰ PHÒNG, KHÔNG BẮT LIỆT KÊ HẠNG MỤC
// Nhiều tuần nhân viên chưa biết sẽ mua gì, chỉ xin một cục dự phòng. Bắt liệt kê hạng mục
// trước khi gửi là bắt họ khai bừa.
$d_dp = VHCP_Don::create_don( 'T9/2026 (8/9-14/9/2026)', 'NV Chỉ Dự Phòng' );
$ma_dp = $d_dp['maDon'];
t( 'chưa có gì thì không gửi được', empty( VHCP_Don::gui_duyet_tam_ung( $ma_dp )['success'] ) );
t( 'nhập dự phòng', ! empty( VHCP_Don::set_du_phong( $ma_dp, 5000000 )['success'] ) );
$g_dp = VHCP_Don::get_don( $ma_dp );
teq( 'không có hạng mục nào', 0, count( $g_dp['lines'] ) );
teq( 'tạm ứng = dự phòng', 5000000, $g_dp['tongCN']['tamUng'] );
t( 'CHỈ dự phòng vẫn gửi xin tạm ứng được', ! empty( VHCP_Don::gui_duyet_tam_ung( $ma_dp )['success'] ) );
teq( 'đơn sang chờ duyệt', 'Chờ duyệt tạm ứng', VHCP_Don::don_row( $ma_dp )['trang_thai'] );
VHCP_Don::delete_don_admin( $ma_dp );

// ------------------------------- NHÂN VIÊN CHỈ THẤY / SỬA ĐƠN CỦA CHÍNH MÌNH
// Đã gặp cảnh một Nhân viên thấy tab Duyệt tạm ứng với đơn của MỌI NGƯỜI kèm nút
// "Gửi tạm ứng" — do bảng phân quyền nạp từ bảng tính cũ bị lệch. Chặn ở MÁY CHỦ.
$d_a = VHCP_Don::create_don( 'T9/2026 (1/9-7/9/2026)', 'NV Của Tôi' );
$d_b = VHCP_Don::create_don( 'T9/2026 (1/9-7/9/2026)', 'NV Người Khác' );
$ma_a = $d_a['maDon']; $ma_b = $d_b['maDon'];
VHCP_Don::add_line( $ma_a, array( 'coso' => 'FARM PHAN THIẾT', 'ngay' => $today, 'phanLoaiTT' => 'Thanh toán cá nhân', 'nhom' => 'Chi phí cơ sở', 'noiDung' => 'Của tôi', 'soLuong' => 1, 'donGia' => 100000, 'thanhTien' => 100000 ) );
VHCP_Don::add_line( $ma_b, array( 'coso' => 'TÀU TÂN PHÚ', 'ngay' => $today, 'phanLoaiTT' => 'Thanh toán cá nhân', 'nhom' => 'Chi phí cơ sở', 'noiDung' => 'Của người khác', 'soLuong' => 1, 'donGia' => 200000, 'thanhTien' => 200000 ) );

VHCP_Auth::dat_vai_tro( 'Nhân viên', 'NV Của Tôi' );
$ds_nv = VHCP_Don::list_dons();
$ten_nv = array();
foreach ( $ds_nv as $x ) { $ten_nv[ $x['nguoiLap'] ] = 1; }
teq( 'nhân viên chỉ thấy đơn của mình', array( 'NV Của Tôi' ), array_keys( $ten_nv ) );
t( 'mở được đơn của mình', ! empty( VHCP_Don::get_don( $ma_a )['success'] ) );
$mo_lo = VHCP_Don::get_don( $ma_b );
t( 'KHÔNG mở được đơn người khác', empty( $mo_lo['success'] ) );
t( 'và nói rõ vì sao', strpos( (string) $mo_lo['error'], 'của người khác' ) !== false, $mo_lo );
t( 'không thêm dòng vào đơn người khác', empty( VHCP_Don::add_line( $ma_b, array( 'coso' => 'TÀU TÂN PHÚ', 'nhom' => 'Chi phí cơ sở', 'noiDung' => 'Chen vào', 'soLuong' => 1, 'donGia' => 1, 'thanhTien' => 1 ) )['success'] ) );
t( 'không gửi xin tạm ứng hộ người khác', empty( VHCP_Don::gui_duyet_tam_ung( $ma_b )['success'] ) );
t( 'không xóa đơn người khác', empty( VHCP_Don::delete_don( $ma_b )['success'] ) );
t( 'vẫn gửi được đơn của mình', ! empty( VHCP_Don::gui_duyet_tam_ung( $ma_a )['success'] ) );

// Cửa API: nhân viên không gọi được việc của người duyệt, dù bảng phân quyền có tích
VHCP_Cfg::set_quyen( array( 'duyetTU' => array( 'Nhân viên' => 1 ), 'capTU' => array( 'Nhân viên' => 1 ) ) );
$tok_nv2 = VHCP_Auth::issue_token( 'NV Của Tôi', 'Nhân viên', '', '' );
teq( 'API: nhân viên duyệt tạm ứng -> 403', 403, api( 'duyetTamUng', array( $ma_a, 'NV Của Tôi' ), $tok_nv2 )['status'] );
teq( 'API: nhân viên cấp tạm ứng -> 403', 403, api( 'capTamUng', array( $ma_a, 'NV Của Tôi' ), $tok_nv2 )['status'] );
teq( 'API: nhân viên trả lại đơn -> 403', 403, api( 'traLaiDon', array( $ma_a, 'x' ), $tok_nv2 )['status'] );
teq( 'API: quản lý duyệt thì được', 200, api( 'duyetTamUng', array( $ma_a, 'QL F' ), $tok_ql )['status'] );
VHCP_Cfg::reset_quyen();
VHCP_Auth::dat_vai_tro( '', '' );
VHCP_Don::delete_don_admin( $ma_a );
VHCP_Don::delete_don_admin( $ma_b );

// ------------------------------- XIN TẠM ỨNG LÀ QUYỀN RIÊNG, KHÔNG DÍNH "SỬA SỐ"
// Luồng chi phí cơ sở: lên đơn (NV) → xin tạm ứng (NV) → duyệt (QL) → cấp (KT) →
// gửi quyết toán (NV) → quyết toán (KT). Nút gửi đơn của NV từng bị gác bởi quyền
// 'suaTU' (sửa SỐ tiền) — bỏ tích quyền đó là NV mất luôn khả năng gửi đơn của mình.
VHCP_Cfg::reset_quyen();   // bài trước có sửa ma trận, về mặc định rồi mới kiểm mặc định
$q0 = VHCP_Cfg::get_quyen();
t( 'có quyền riêng "xin tạm ứng"', isset( $q0['xinTU'] ) );
t( 'mặc định Nhân viên được xin tạm ứng', ! empty( $q0['xinTU']['Nhân viên'] ) );
t( 'mặc định Nhân viên KHÔNG được duyệt tạm ứng', empty( $q0['duyetTU']['Nhân viên'] ) );
t( 'mặc định Quản lý được duyệt tạm ứng', ! empty( $q0['duyetTU']['Quản lý'] ) );
t( 'mặc định Kế toán cá nhân được cấp tạm ứng', ! empty( $q0['capTU']['Kế toán cá nhân'] ) );
t( 'mặc định Nhân viên được gửi quyết toán', ! empty( $q0['guiQT']['Nhân viên'] ) );
t( 'mặc định Kế toán xác nhận quyết toán', ! empty( $q0['xacNhanQT']['Kế toán cá nhân'] ) );
/* 🔴 CẢ HAI VAI KẾ TOÁN ĐỀU QUYẾT TOÁN ĐƯỢC.
   Anh Thắng 28/08/2026 gửi hai ảnh cùng một khối "Chờ quyết toán": bên tài khoản của anh có
   nút ✔ Duyệt, bên tài khoản kế toán chỉ còn "Chi tiết" — *"Sao tài khoản kế toán lại không
   được quyết toán. bật kế toán quyết toán."*
   Chữ "(cá nhân)" trong tên hành động nói về PHẦN TIỀN của đơn (đối ứng 141 cá nhân, khác
   phần 331 nhà cung cấp), không phải nói về vai. Hai thứ trùng chữ nên dễ đọc nhầm — và đã
   đọc nhầm suốt từ bản đầu. */
t( '🔴 mặc định Kế toán NCC CŨNG xác nhận quyết toán được',
	! empty( $q0['xacNhanQT']['Kế toán NCC'] ), $q0['xacNhanQT'] );
t( 'nhưng Nhân viên thì không', empty( $q0['xacNhanQT']['Nhân viên'] ) );
/* Và ĐỔI LUÔN TÊN hành động: "Xác nhận quyết toán (cá nhân)" đọc như tên một vai. */
$ten_qt = '';
foreach ( VHCP_Cfg::actions() as $a_qt ) {
	if ( 'xacNhanQT' === $a_qt['key'] ) { $ten_qt = $a_qt['ten']; }
}
t( 'tên hành động nói rõ "cá nhân" là PHẦN TIỀN, không phải tên vai',
	strpos( $ten_qt, 'phần cá nhân của đơn' ) !== false, $ten_qt );

/* ---- VÁ MỘT LẦN cho site ĐÃ LƯU bảng quyền ----
   Đổi `def` chỉ ăn với site chưa từng lưu bảng quyền. Site của anh Thắng đã lưu, nên `def` bị
   bỏ qua hoàn toàn và nút Duyệt vẫn không hiện — đó là chỗ "sửa mặc định" trông như đã sửa
   xong mà thực ra chưa đụng tới gì. */
delete_option( VHCP_Cfg::CO_VA_QT );
VHCP_Cfg::set_quyen( array( 'xacNhanQT' => array( 'Kế toán cá nhân' => 1 ) ) );  /* NCC = 0 */
$q_truoc = VHCP_Cfg::get_quyen();
t( 'dựng lại đúng cảnh: bảng đã lưu, Kế toán NCC KHÔNG có quyền',
	empty( $q_truoc['xacNhanQT']['Kế toán NCC'] ), $q_truoc['xacNhanQT'] );
$so_va = VHCP_Cfg::va_quyen_quyet_toan();
t( 'bản vá chạy và có bật ô nào đó', (int) $so_va > 0, $so_va );
$q_sau = VHCP_Cfg::get_quyen();
t( '🔴 vá xong thì Kế toán NCC quyết toán được',
	! empty( $q_sau['xacNhanQT']['Kế toán NCC'] ), $q_sau['xacNhanQT'] );
t( 'Kế toán cá nhân vẫn nguyên', ! empty( $q_sau['xacNhanQT']['Kế toán cá nhân'] ) );
/* 🔴 CHỈ BẬT, KHÔNG BAO GIỜ TẮT, VÀ CHỈ ĐỤNG ĐÚNG MỘT HÀNH ĐỘNG. */
t( 'Nhân viên KHÔNG tự dưng quyết toán được', empty( $q_sau['xacNhanQT']['Nhân viên'] ) );
t( 'các ô của hành động KHÁC giữ nguyên từng con số',
	empty( $q_sau['duyetTU']['Nhân viên'] ) && empty( $q_sau['capTU']['Nhân viên'] ) );
/* 🔴 SOI ĐÚNG CỘT KẾ TOÁN CỦA HÀNH ĐỘNG KHÁC. Bản vá bật theo CỘT (vai kế toán), nên nếu nó
   quét nhầm sang mọi HÀNG thì chỗ lộ ra là ô [hành động khác × vai kế toán] — soi cột Nhân
   viên thì không bao giờ thấy, vì cột ấy vốn không nằm trong tầm với của bản vá. */
t( '🔴 "Duyệt tạm ứng" của Kế toán NCC vẫn tắt như trước',
	empty( $q_sau['duyetTU']['Kế toán NCC'] ), $q_sau['duyetTU'] );
t( 'và "Cấp tạm ứng" của Kế toán NCC cũng vậy',
	empty( $q_sau['capTU']['Kế toán NCC'] ), $q_sau['capTU'] );

/* Site CHƯA TỪNG lưu bảng quyền: `def` mới đã đủ, bản vá không có gì để làm — và tuyệt đối
   không được ghi đè một bảng rỗng lên chỗ chưa có gì. */
delete_option( VHCP_Cfg::CO_VA_QT );
VHCP_Cfg::write( VHCP_Cfg::QUYEN, array(), false );
teq( '🔴 site chưa lưu bảng quyền thì bản vá không làm gì', 0, VHCP_Cfg::va_quyen_quyet_toan() );
$q_trong = VHCP_Cfg::get_quyen();
t( 'và quyền vẫn chạy theo mặc định mới',
	! empty( $q_trong['xacNhanQT']['Kế toán NCC'] ), $q_trong['xacNhanQT'] );
delete_option( VHCP_Cfg::CO_VA_QT );
VHCP_Cfg::reset_quyen();
VHCP_Cfg::set_quyen( array( 'xacNhanQT' => array( 'Kế toán cá nhân' => 1 ) ) );
VHCP_Cfg::va_quyen_quyet_toan();
$q_sau2 = VHCP_Cfg::get_quyen();
t( 'dựng lại trạng thái đã vá để phép thử sau chạy tiếp',
	! empty( $q_sau2['xacNhanQT']['Kế toán NCC'] ) );
/* ⚠️ CHẠY ĐÚNG MỘT LẦN. Chạy lại mỗi lần nạp trang thì ai bỏ tích hôm nay, sáng mai nó lại tự
   bật — và không ai hiểu vì sao bảng phân quyền không nghe lời. */
VHCP_Cfg::set_quyen( array( 'xacNhanQT' => array( 'Kế toán cá nhân' => 1 ) ) );
teq( '🔴 gọi lại lần hai thì KHÔNG làm gì nữa', 0, VHCP_Cfg::va_quyen_quyet_toan() );
t( 'và ô người ta vừa bỏ tích vẫn ở nguyên trạng thái bỏ tích',
	empty( VHCP_Cfg::get_quyen()['xacNhanQT']['Kế toán NCC'] ) );
delete_option( VHCP_Cfg::CO_VA_QT );
VHCP_Cfg::reset_quyen();
// Bỏ tích "sửa số tạm ứng" thì quyền xin tạm ứng KHÔNG bị mất theo
VHCP_Cfg::set_quyen( array( 'xinTU' => array( 'Nhân viên' => 1 ), 'duyetTU' => array( 'Quản lý' => 1 ) ) );
$q0b = VHCP_Cfg::get_quyen();
t( 'bỏ quyền sửa số thì vẫn xin được tạm ứng', ! empty( $q0b['xinTU']['Nhân viên'] ) && empty( $q0b['suaTU']['Nhân viên'] ) );
VHCP_Cfg::reset_quyen();

// ------------------------------- ĐẶT LẠI PHÂN QUYỀN VỀ MẶC ĐỊNH
// Bảng CH_Quyen nạp từ bảng tính cũ lệch cột thì một vai trò mất quyền mà không ai biết —
// biểu hiện đúng là "nhập xong không thấy nút gửi duyệt".
VHCP_Cfg::set_quyen( array( 'suaTU' => array( 'Quản lý' => 1 ) ) );   // Nhân viên bị mất quyền
$q1 = VHCP_Cfg::get_quyen();
t( 'dựng lại đúng cảnh mất quyền', empty( $q1['suaTU']['Nhân viên'] ) );
$rq = VHCP_Cfg::reset_quyen();
t( 'đặt lại mặc định chạy được', ! empty( $rq['success'] ) );
t( 'có báo số hành động', (int) $rq['soHanhDong'] > 0, $rq );
$q2 = VHCP_Cfg::get_quyen();
t( 'Nhân viên có lại quyền sửa số tạm ứng', ! empty( $q2['suaTU']['Nhân viên'] ) );
t( 'Quản lý vẫn có quyền đó', ! empty( $q2['suaTU']['Quản lý'] ) );
t( 'Nhân viên KHÔNG tự dưng được duyệt tạm ứng', empty( $q2['duyetTU']['Nhân viên'] ) );

// ---------------------------------------------------------------- DỌN LOẠI CHƯA KHAI MÃ
// Đặt CUỐI CÙNG: phép thử này xóa mọi loại chưa khai mã, kể cả danh mục dựng sẵn từ nhóm
// mặt hàng — chạy giữa bài là các phần sau mất danh mục.
VHCP_Cfg::them_loai_neu_thieu( array( 'Nguyễn Hữu Thọ, Nguyễn Bá Tuấn', 'Cấp Mạng VNPT', 'Nhân Công' ) );
$co_rac = false;
foreach ( VHCP_Cfg::get_config()['loaiChiPhi'] as $x ) { if ( $x['ten'] === 'Cấp Mạng VNPT' ) { $co_rac = true; } }
t( 'nạp xong thì loại tự sinh có trong danh mục', $co_rac );
$kc = VHCP_Cfg::khai_cho_coso( array( 'ten' => 'Nhân Công', 'tkNo' => '64121', 'cosos' => array( 'FUNZONE ADVENTURE' ) ) );
t( 'khai mã cho loại tự sinh', ! empty( $kc['success'] ), $kc );
$dl = VHCP_Cfg::xoa_loai_tu_tao();
t( 'dọn được ít nhất 2 loại rác', (int) $dl['xoa'] >= 2, $dl );
$ten_sau = array();
foreach ( VHCP_Cfg::get_config()['loaiChiPhi'] as $x ) { $ten_sau[] = $x['ten']; }
t( 'loại rác đã đi', ! in_array( 'Cấp Mạng VNPT', $ten_sau, true ) );
t( 'loại đã khai mã thì GIỮ LẠI', in_array( 'Nhân Công', $ten_sau, true ) );

// ================== HƯỚNG DẪN CÀI PHẢI KHỚP MÃ (anh Thắng cài chi phí trước, 22/08/2026)
/* Hướng dẫn viết một đằng mã chạy một nẻo là kiểu sai tệ nhất: người làm theo đúng từng chữ mà
   vẫn hỏng, rồi đi tìm lỗi ở chỗ không có lỗi. Mấy giá trị dưới đây là thứ người dùng GÕ THEO
   hướng dẫn, nên phải khoá lại. */
$goc_hd = dirname( dirname( __DIR__ ) ) . '/khh-chamcong-firmware';
if ( ! is_dir( $goc_hd ) ) { $goc_hd = dirname( dirname( __DIR__ ) ); }
$hd_cp   = file_get_contents( $goc_hd . '/docs/CAI-CHI-PHI.md' );
/* ⚠️ So cụm chữ trong tài liệu thì phải GỘP KHOẢNG TRẮNG trước: văn bản Markdown xuống dòng
   giữa câu, nên "…có đường\nliên kết…" không khớp với cùng câu đó viết liền. Bản đầu so thẳng
   và trượt oan — tài liệu đúng, phép so mới sai. */
$hd_cp_1dong = preg_replace( '/\s+/u', ' ', $hd_cp );
$ma_app  = file_get_contents( $goc_hd . '/wordpress/vhcp-chi-phi/includes/class-vhcp-app.php' );
$ma_ad   = file_get_contents( $goc_hd . '/wordpress/vhcp-chi-phi/includes/class-vhcp-admin.php' );
$ma_sh   = file_get_contents( $goc_hd . '/wordpress/vhcp-chi-phi/includes/class-vhcp-sheet.php' );

t( 'có hướng dẫn cài riêng cho app chi phí', strlen( $hd_cp ) > 1500 );
t( 'đường dẫn mặc định trong hướng dẫn khớp mã (chi-phi)',
	strpos( $hd_cp_1dong, '`chi-phi`' ) !== false && strpos( $ma_app, "'chi-phi'" ) !== false );
t( 'múi giờ mặc định trong hướng dẫn khớp mã (Asia/Bangkok)',
	strpos( $hd_cp_1dong, 'Asia/Bangkok' ) !== false && strpos( $ma_ad, "'Asia/Bangkok'" ) !== false );
t( 'hướng dẫn dặn chia sẻ bảng tính đúng câu plugin sẽ báo',
	strpos( $hd_cp_1dong, 'Bất kỳ ai có đường liên kết' ) !== false
	&& strpos( $ma_sh, 'Bất kỳ ai có đường liên kết' ) !== false );
/* 🔴 App này KHÔNG cần hằng nào trong wp-config.php — khác hẳn app chấm công. Nói sai chỗ này
   là anh Thắng đi thêm hai bước thừa, hoặc tệ hơn: tưởng thiếu khoá nên đi tìm khoá. */
$hang_cp = array();
foreach ( glob( $goc_hd . '/wordpress/vhcp-chi-phi/includes/*.php' ) as $f_cp ) {
	if ( preg_match_all( "/defined\(\s*'(VHCP_[A-Z_]+)'/", file_get_contents( $f_cp ), $m_cp ) ) {
		foreach ( $m_cp[1] as $h_cp ) {
			if ( ! in_array( $h_cp, array( 'VHCP_DIR', 'VHCP_URL', 'VHCP_VERSION', 'VHCP_FILE' ), true ) ) {
				$hang_cp[] = $h_cp;
			}
		}
	}
}
t( 'app chi phí không đòi hằng nào trong wp-config.php — đúng như hướng dẫn nói',
	count( $hang_cp ) === 0, implode( ', ', array_unique( $hang_cp ) ) );
t( 'và hướng dẫn nói rõ điều đó',
	strpos( $hd_cp_1dong, 'KHÔNG cần khoá nào trong `wp-config.php`' ) !== false );
/* Bộ nạp khớp theo TÊN CỘT, không theo thứ tự — hướng dẫn nói vậy thì mã phải vậy. */
t( 'bộ nạp khớp theo tên cột (không theo thứ tự), đúng như hướng dẫn',
	strpos( $hd_cp_1dong, 'khớp theo TÊN CỘT' ) !== false
	&& strpos( file_get_contents( $goc_hd . '/wordpress/vhcp-chi-phi/includes/class-vhcp-nap.php' ),
		'NẠP THEO TÊN TIÊU ĐỀ' ) !== false );

/* ============ BÙ TRỪ GỘP CẢ TUẦN THEO QUẢN LÝ (24/08/2026) ============
   Người cầm sổ chi và mang số dư TK 141 là QUẢN LÝ DUYỆT, không phải người lập đơn. Cơ sở chỉ
   là nơi tạo đơn. Tiền thừa của từng gian có trả về kế toán, nhưng quản lý vẫn đang cần tiền
   nên khoản đó ghi nhận lại và chuyển sang tuần sau cho chính quản lý ấy.

   Luật cũ dò theo người lập và chỉ lấy MỘT đơn gần nhất — sai hai đường: số dư của quản lý bị
   xé nhỏ theo từng người lập, và tuần nào quản lý duyệt nhiều đơn thì phần còn lại rơi mất.
   Khối này dựng đúng tình huống đó: một quản lý, hai cơ sở, hai người lập, cùng một tuần.

   Đặt ở CUỐI tệp: mỗi đơn dựng thêm đều chảy vào báo cáo và các phép đếm phía trên, chèn vào
   giữa là làm hỏng chúng — đã dính một lần khi viết khối này. */
$_ql   = 'Quản Lý Vùng ' . substr( md5( 'ql' ), 0, 4 );
$_ky_a = 'T10/2026 (06/10-12/10/2026)';
$_ky_b = 'T10/2026 (13/10-19/10/2026)';

$_mk = array();
foreach ( array( array( 'NV Bù Trừ Một', 'FARM PHAN THIẾT', 1000000, 700000 ),
                 array( 'NV Bù Trừ Hai', 'TÀU TÂN PHÚ',      2000000, 2500000 ) ) as $_c ) {
	$_m = VHCP_Don::create_don( $_ky_a, $_c[0] )['maDon'];
	VHCP_Don::add_line( $_m, array( 'coso' => $_c[1], 'ngay' => $today, 'phanLoaiTT' => 'Thanh toán cá nhân', 'nhom' => 'Nguyên vật liệu', 'noiDung' => 'Hàng tuần A', 'soLuong' => 1, 'donGia' => $_c[2], 'thanhTien' => $_c[2] ) );
	VHCP_Don::gui_duyet_tam_ung( $_m );
	VHCP_Don::duyet_tam_ung( $_m, $_ql, '' );
	VHCP_Don::cap_tam_ung( $_m, 'Lê Kế Toán', 'Tiền mặt' );
	VHCP_Don::set_line_thuc_mua( VHCP_Don::get_don( $_m )['lines'][0]['id'], $_c[3], 'Lê Kế Toán' );
	$_mk[] = $_m;
}

/* Không ghim số cứng: bản thân hai đơn tuần A cũng mang bù trừ của riêng chúng, nên chênh lệch
   thật phụ thuộc dữ liệu dựng phía trên. Đọc số thật rồi kiểm LUẬT GỘP — đó mới là thứ vừa đổi. */
$_ch1 = VHCP_Util::num( VHCP_Don::get_don( $_mk[0] )['tongCN']['chenhLech'] );
$_ch2 = VHCP_Util::num( VHCP_Don::get_don( $_mk[1] )['tongCN']['chenhLech'] );
t( 'dựng được hai đơn khác dấu nhau', $_ch1 > 0 && $_ch2 < 0, array( $_ch1, $_ch2 ) );

$_m_sau = VHCP_Don::create_don( $_ky_b, 'NV Bù Trừ Một' )['maDon'];
VHCP_Don::add_line( $_m_sau, array( 'coso' => 'FARM PHAN THIẾT', 'ngay' => $today, 'phanLoaiTT' => 'Thanh toán cá nhân', 'nhom' => 'Nguyên vật liệu', 'noiDung' => 'Hàng tuần B', 'soLuong' => 1, 'donGia' => 900000, 'thanhTien' => 900000 ) );
$_bt = VHCP_Don::get_don( $_m_sau )['don']['buTruAuto'];
teq( 'gộp đủ 2 đơn của quản lý trong tuần trước', 2, (int) $_bt['soDon'] );
teq( 'tổng chênh = cộng cả hai đơn, không lấy mỗi đơn gần nhất', $_ch1 + $_ch2, VHCP_Util::num( $_bt['chenhTruoc'] ) );
teq( 'bù trừ mang dấu ngược tổng chênh', -( $_ch1 + $_ch2 ), VHCP_Util::num( $_bt['so'] ) );
teq( 'quy về đúng quản lý', $_ql, (string) $_bt['quanLy'] );
teq( 'lấy đúng tuần trước', $_ky_a, (string) $_bt['kyTruoc'] );

/* Quản lý KHÁC không ăn theo số dư của quản lý này — dù cùng cơ sở, cùng tuần. */
$_m_ql2 = VHCP_Don::create_don( $_ky_b, 'NV Bù Trừ Một' )['maDon'];
VHCP_Don::add_line( $_m_ql2, array( 'coso' => 'FARM PHAN THIẾT', 'ngay' => $today, 'phanLoaiTT' => 'Thanh toán cá nhân', 'nhom' => 'Nguyên vật liệu', 'noiDung' => 'Hàng tuần B2', 'soLuong' => 1, 'donGia' => 500000, 'thanhTien' => 500000 ) );
VHCP_Don::gui_duyet_tam_ung( $_m_ql2 );
VHCP_Don::duyet_tam_ung( $_m_ql2, 'Quản Lý Khác Hẳn', '' );
$_bt2 = VHCP_Don::get_don( $_m_ql2 )['don']['buTruAuto'];
teq( 'quản lý khác: không mang số dư của quản lý kia', 0, VHCP_Util::num( $_bt2['so'] ) );
teq( 'và quy đúng về quản lý của chính đơn đó', 'Quản Lý Khác Hẳn', (string) $_bt2['quanLy'] );
t( 'đơn đã duyệt thì KHÔNG còn là dự kiến', empty( $_bt2['duKien'] ), $_bt2 );

/* Đơn CÙNG TUẦN của chính quản lý đó không được tính là "tuần trước" — tính vào thì bù trừ ăn
   lên chính nó và con số chạy vòng, đúng hiện tượng anh Thắng gặp (2.616.000 lặp mãi).

   Phải DUYỆT $_m_sau trước đã: nếu tuần B chưa có đơn nào do chính $_ql duyệt thì bỏ chốt
   "cùng tuần" đi cũng chẳng đổi gì, và phép thử xanh trong khi mã sai — đã dính đúng vậy khi
   viết khối này lần đầu. */
VHCP_Don::gui_duyet_tam_ung( $_m_sau );
VHCP_Don::duyet_tam_ung( $_m_sau, $_ql, '' );
VHCP_Don::cap_tam_ung( $_m_sau, 'Lê Kế Toán', 'Tiền mặt' );
VHCP_Don::set_line_thuc_mua( VHCP_Don::get_don( $_m_sau )['lines'][0]['id'], 100000, 'Lê Kế Toán' );
t( 'tuần B giờ đã có đơn của chính quản lý đó', VHCP_Util::num( VHCP_Don::get_don( $_m_sau )['tongCN']['chenhLech'] ) != 0 );

$_m_cung = VHCP_Don::create_don( $_ky_b, 'NV Bù Trừ Hai' )['maDon'];
VHCP_Don::add_line( $_m_cung, array( 'coso' => 'TÀU TÂN PHÚ', 'ngay' => $today, 'phanLoaiTT' => 'Thanh toán cá nhân', 'nhom' => 'Nguyên vật liệu', 'noiDung' => 'Hàng tuần B3', 'soLuong' => 1, 'donGia' => 400000, 'thanhTien' => 400000 ) );
VHCP_Don::gui_duyet_tam_ung( $_m_cung );
VHCP_Don::duyet_tam_ung( $_m_cung, $_ql, '' );
$_bt3 = VHCP_Don::get_don( $_m_cung )['don']['buTruAuto'];
teq( 'vẫn lấy tuần A, không lấy đơn cùng tuần B', $_ky_a, (string) $_bt3['kyTruoc'] );
teq( 'và ra cùng con số với đơn kia của quản lý', VHCP_Util::num( $_bt['so'] ), VHCP_Util::num( $_bt3['so'] ) );

/* ══════════════════════════════════════════════════════════════════════════════════════════
   NHÂN VIÊN SỬA ĐƯỢC ĐƠN Ở MỌI TRẠNG THÁI CHƯA CHỐT SỔ
   Anh Thắng 26/08/2026: *"giờ sẽ cho nhân viên được phép sửa đơn. trừ khi ở trạng thái đã quyết
   toán mới không được sửa, còn lại nhân viên đều được sửa và bổ sung đơn."*                    */

/* ---- ranh giới: chỉ hai trạng thái là chốt ---- */
teq( 'chốt sổ đúng hai trạng thái', array( 'Đã quyết toán', 'Đã xuất MISA' ), VHCP_Don::TT_CHOT );
foreach ( array( 'Nháp', 'Chờ duyệt tạm ứng', 'Chờ cấp tạm ứng', 'Đã cấp tạm ứng', 'Chờ quyết toán' ) as $_st_mo ) {
	t( 'trạng thái "' . $_st_mo . '" KHÔNG phải chốt sổ', ! VHCP_Don::da_chot( $_st_mo ) );
}
foreach ( VHCP_Don::TT_CHOT as $_st_dong ) {
	t( 'trạng thái "' . $_st_dong . '" là chốt sổ', VHCP_Don::da_chot( $_st_dong ) );
}
/* 🔴 Thanh bước trên màn hình đọc `TT_LUONG`. Thiếu một trạng thái ở đây là thanh vẽ ra một
   luồng không có thật — và người ta tin cái hình hơn tin câu chữ. */
foreach ( VHCP_Don::TT_CHOT as $_x ) {
	t( 'luồng có nhắc "' . $_x . '"', in_array( $_x, VHCP_Don::TT_LUONG, true ) );
}
teq( 'luồng bắt đầu từ Nháp', 'Nháp', VHCP_Don::TT_LUONG[0] );
teq( 'và kết ở Đã xuất MISA', 'Đã xuất MISA', VHCP_Don::TT_LUONG[ count( VHCP_Don::TT_LUONG ) - 1 ] );

$_ky_s = 'T9/2026 (7/9-13/9/2026)';
$_ms = VHCP_Don::create_don( $_ky_s, 'NV Sửa Đơn' )['maDon'];
$_ls = VHCP_Don::add_line( $_ms, array( 'coso' => 'TÀU TÂN PHÚ', 'ngay' => $today,
	'phanLoaiTT' => 'Thanh toán cá nhân', 'nhom' => 'Nguyên vật liệu',
	'noiDung' => 'Hàng xin', 'soLuong' => 1, 'donGia' => 500000, 'thanhTien' => 500000 ) );
t( 'thêm được hạng mục xin lúc Nháp', ! empty( $_ls['success'] ), $_ls );
teq( 'và nó là hạng mục XIN, không phải phát sinh', 0, (int) $_ls['phatSinh'] );

/* 🔴 CHỖ TRƯỚC ĐÂY VƯỚNG: gửi duyệt xong là khoá cứng. Quản lý bảo "sửa lại rồi anh duyệt" mà
   nhân viên không sửa được, phải nhờ người khác trả đơn về Nháp. */
VHCP_Don::gui_duyet_tam_ung( $_ms );
teq( 'đơn đã gửi duyệt', 'Chờ duyệt tạm ứng', VHCP_Don::get_don( $_ms )['don']['trangThai'] );
teq( 'và vẫn còn sửa được', '', VHCP_Don::vi_sao_khong_sua( $_ms ) );
$_r = VHCP_Don::update_line( $_ls['id'], array( 'noiDung' => 'Hàng xin (đã sửa)',
	'soLuong' => 1, 'donGia' => 600000, 'thanhTien' => 600000 ) );
t( '🔴 sửa được hạng mục xin khi ĐANG CHỜ DUYỆT', ! empty( $_r['success'] ), $_r );
$_r = VHCP_Don::add_line( $_ms, array( 'coso' => 'TÀU TÂN PHÚ', 'ngay' => $today,
	'phanLoaiTT' => 'Thanh toán cá nhân', 'nhom' => 'Nguyên vật liệu',
	'noiDung' => 'Bổ sung lúc chờ duyệt', 'soLuong' => 1, 'donGia' => 100000, 'thanhTien' => 100000 ) );
t( '🔴 bổ sung được dòng khi đang chờ duyệt', ! empty( $_r['success'] ), $_r );
/* ⚠️ Dòng thêm SAU khi gửi duyệt là PHÁT SINH, không phải hạng mục xin. Phân loại theo trạng
   thái lúc thêm chứ không hỏi giao diện — hỏi giao diện là ai cũng khai được một khoản mua thêm
   thành "đã xin từ đầu", và bảng đối chiếu thừa/thiếu mất nghĩa. */
teq( 'và dòng ấy là PHÁT SINH', 1, (int) $_r['phatSinh'] );

/* Đi tiếp tới "Chờ quyết toán" — vẫn phải sửa được. */
VHCP_Don::duyet_tam_ung( $_ms, 'Quản lý S', '' );
teq( 'sửa được cả khi đã duyệt, chờ cấp tiền', '', VHCP_Don::vi_sao_khong_sua( $_ms ) );
VHCP_Don::cap_tam_ung( $_ms, 'Lê Kế Toán', 'Tiền mặt' );
VHCP_Don::set_line_thuc_mua( $_ls['id'], 600000, 'Lê Kế Toán' );
VHCP_Don::gui_quyet_toan( $_ms );
teq( 'đơn đang chờ quyết toán', 'Chờ quyết toán', VHCP_Don::get_don( $_ms )['don']['trangThai'] );
teq( 'vẫn còn sửa được', '', VHCP_Don::vi_sao_khong_sua( $_ms ) );
$_r = VHCP_Don::add_line( $_ms, array( 'coso' => 'TÀU TÂN PHÚ', 'ngay' => $today,
	'phanLoaiTT' => 'Thanh toán cá nhân', 'nhom' => 'Nguyên vật liệu',
	'noiDung' => 'Bổ sung lúc chờ quyết toán', 'soLuong' => 1, 'donGia' => 50000, 'thanhTien' => 50000 ) );
t( '🔴 bổ sung được dòng khi đang chờ quyết toán', ! empty( $_r['success'] ), $_r );

/* ---- và ĐÃ QUYẾT TOÁN thì thôi ---- */
VHCP_Don::xac_nhan_quyet_toan_cn( $_ms, 'Lê Kế Toán', 'Hoàn lại', 0 );
teq( 'đơn đã quyết toán', 'Đã quyết toán', VHCP_Don::get_don( $_ms )['don']['trangThai'] );
$_vs = VHCP_Don::vi_sao_khong_sua( $_ms );
t( '🔴 đã quyết toán thì KHÔNG sửa được nữa', '' !== $_vs, $_vs );
/* ⚠️ Câu từ chối phải nói ra ĐANG Ở TRẠNG THÁI NÀO — "không sửa được" trần trụi thì người dùng
   không biết là do đơn đã chốt, do vai trò mình, hay do phần mềm lỗi. */
t( 'và câu từ chối nói rõ đang ở trạng thái nào',
	false !== strpos( $_vs, 'Đã quyết toán' ), $_vs );
$_r = VHCP_Don::add_line( $_ms, array( 'coso' => 'TÀU TÂN PHÚ', 'ngay' => $today,
	'phanLoaiTT' => 'Thanh toán cá nhân', 'nhom' => 'Nguyên vật liệu',
	'noiDung' => 'Lén thêm sau khi chốt', 'soLuong' => 1, 'donGia' => 1, 'thanhTien' => 1 ) );
t( '🔴 thêm dòng vào đơn đã chốt bị chối', empty( $_r['success'] ), $_r );
$_r = VHCP_Don::update_line( $_ls['id'], array( 'noiDung' => 'Lén sửa', 'soLuong' => 1,
	'donGia' => 999999, 'thanhTien' => 999999 ) );
t( '🔴 sửa dòng của đơn đã chốt bị chối', empty( $_r['success'] ), $_r );
$_sau = null;
foreach ( VHCP_Don::get_don( $_ms )['lines'] as $_ln ) {
	if ( $_ln['id'] === $_ls['id'] ) { $_sau = $_ln; }
}
teq( 'và số tiền dòng ấy còn nguyên', 600000, VHCP_Util::num( $_sau['thanhTien'] ) );


// ================================================================ SƠ ĐỒ BẢNG KHAI HAI NƠI
/* 🔴 BỘ KHUNG THỬ GÕ TAY LẠI SƠ ĐỒ BẢNG (SQLite), plugin khai sơ đồ thật (MySQL) — HAI NƠI.
   Chú thích ngay trong `wp-stub.php` đã cảnh báo đúng cái bẫy này, và nó đã sập một lần bên
   chấm công ("table has no column named ma_nv"). Bên chi phí thì các bảng vẫn còn gõ tay.
   Chuyển hẳn sang dựng từ sơ đồ thật là một việc riêng; trong lúc chờ, ít nhất phải có một
   phép ĐỐI CHIẾU: thêm cột vào plugin mà quên thêm vào bộ khung thì ĐỎ NGAY ở đây, kèm tên
   cột thiếu — thay vì đỏ ở một phép thử không liên quan, với câu lỗi SQL không ai đọc.

   ⚠️ Đọc cột từ CHÍNH hai chuỗi CREATE TABLE, không gõ tay danh sách cột lần thứ ba. */
function _cot_trong_create( $sql, $bang ) {
	if ( ! preg_match( '/CREATE TABLE\s+\S*' . preg_quote( $bang, '/' ) . '\s*\((.*)\)\s*$/s', trim( $sql ), $m ) ) {
		return array();
	}
	$than = $m[1];
	$than = preg_replace( '#/\*.*?\*/#s', '', $than );      // bỏ chú thích trong sơ đồ
	$ra = array();
	foreach ( explode( "\n", $than ) as $d ) {
		$d = trim( $d );
		if ( '' === $d || preg_match( '/^(PRIMARY|UNIQUE|KEY|INDEX)\b/i', $d ) ) { continue; }
		if ( preg_match( '/^([a-z_][a-z0-9_]*)\s/i', $d, $mm ) ) { $ra[] = strtolower( $mm[1] ); }
	}
	return $ra;
}
/* Sơ đồ thật nằm trong `install()` dạng chuỗi ghép — đọc thẳng từ MÃ NGUỒN. Xấu, nhưng thà
   đọc từ đúng một nguồn còn hơn gõ tay danh sách cột ở nơi thứ ba. */
$_ma_db  = file_get_contents( VHCP_DIR . 'includes/class-vhcp-db.php' );
$_ddl_that = preg_match( "#self::t\( 'don' \) \. \" \((.*?)\) \\\$c\";#s", $_ma_db, $_mddl )
	? $_mddl[1] : '';
t( 'đọc được sơ đồ bảng `don` của plugin', '' !== $_ddl_that, substr( (string) $_ddl_that, 0, 80 ) );
$_cot_that = _cot_trong_create( "CREATE TABLE don (" . $_ddl_that . ")", 'don' );
t( 'sơ đồ thật có ít nhất 25 cột', count( $_cot_that ) >= 25, $_cot_that );
$_cot_thu = array();
foreach ( VHCP_DB::rows( 'PRAGMA table_info(' . VHCP_DB::t( 'don' ) . ')' ) as $r ) {
	$_cot_thu[] = strtolower( (string) $r['name'] );
}
$_thieu = array_values( array_diff( $_cot_that, $_cot_thu ) );
t( '🔴 bộ khung thử có ĐỦ cột của bảng `don` trong plugin', ! $_thieu,
	'thiếu ở tools/test/wp-stub.php: ' . implode( ', ', $_thieu ) );

// ================================================================ ĐƠN VỊ (K&H · POSH)
/* Anh Thắng 26/08/2026:
     *"Anh có thêm bộ Phận Posh sẽ lên chi trong này. Bộ phận khác nên sẽ tách biệt không xem
     được doanh thu của nhau."*
     *"Kế toán Posh chỉ thấy chi phí Posh. Còn kế toán cá nhân được set thấy cả bộ phận thì
     nhìn chung luôn."*
     *"Bên Posh người duyệt là Quản Lý Posh."*

   🔴 KHỐI NÀY CANH ĐÚNG MỘT CÂU HỎI: số của POSH có lọt sang màn K&H được không, và ngược lại.
      Và canh ở CẢ HAI TẦNG — danh sách (mắt không thấy) và cửa vào từng đơn (tay không với
      tới). Chỉ canh tầng thứ nhất thì gõ mã đơn lên thanh địa chỉ là qua. */

$_vt_dv = VHCP_Auth::vai_tro();

/* Bốn người: một cặp K&H, một cặp POSH, và một kế toán nhìn chung cả hai. */
VHCP_Cfg::write( VHCP_Cfg::USER, array(
	array( 'Admin',        '1111', 'Admin',    '', '', '', '', '',     '' ),
	array( 'NV K&H',       '2001', 'Nhân viên', '', '', '', '', 'K&H',  '' ),
	array( 'NV POSH',      '2002', 'Nhân viên', '', '', '', '', 'POSH', '' ),
	array( 'KT Cá Nhân',   '2003', 'Kế toán cá nhân', '', '', '', '', 'K&H',  '' ),
	array( 'KT POSH',      '2004', 'Kế toán cá nhân', '', '', '', '', 'POSH', 'POSH' ),
	array( 'QL POSH',      '2005', 'Quản lý',  '', '', '', '', 'POSH', 'POSH' ),
	array( 'KT Chỉ K&H',   '2006', 'Kế toán cá nhân', '', '', '', '', 'K&H',  'K&H' ),
) );

teq( 'nhà của người khai POSH', 'POSH', VHCP_DonVi::cua_nguoi( 'NV POSH' ) );
teq( 'người chưa khai đơn vị rơi về nhà mặc định', 'K&H', VHCP_DonVi::cua_nguoi( 'Admin' ) );
teq( 'người không có trong sổ cũng về nhà mặc định', 'K&H', VHCP_DonVi::cua_nguoi( 'Ai Đó Lạ' ) );
t( 'danh sách đơn vị có cả K&H lẫn POSH',
	in_array( 'K&H', VHCP_DonVi::ds(), true ) && in_array( 'POSH', VHCP_DonVi::ds(), true ),
	VHCP_DonVi::ds() );

/* ---- đơn mang đơn vị của NGƯỜI LẬP ---- */
VHCP_Auth::dat_vai_tro( 'Nhân viên', 'NV POSH' );
$_dp = VHCP_Don::create_don( 'T8/2026 (17/8-23/8/2026)', 'NV POSH' );
$_mp = $_dp['maDon'];
VHCP_Don::add_line( $_mp, array( 'coso' => 'FARM PHAN THIẾT', 'ngay' => $today,
	'phanLoaiTT' => 'Thanh toán cá nhân', 'nhom' => 'Chi phí cơ sở', 'noiDung' => 'Đèn POSH',
	'soLuong' => 1, 'donGia' => 500000, 'thanhTien' => 500000 ) );

VHCP_Auth::dat_vai_tro( 'Nhân viên', 'NV K&H' );
$_dk = VHCP_Don::create_don( 'T8/2026 (17/8-23/8/2026)', 'NV K&H' );
$_mk = $_dk['maDon'];
VHCP_Don::add_line( $_mk, array( 'coso' => 'FARM PHAN THIẾT', 'ngay' => $today,
	'phanLoaiTT' => 'Thanh toán cá nhân', 'nhom' => 'Chi phí cơ sở', 'noiDung' => 'Đèn K&H',
	'soLuong' => 1, 'donGia' => 700000, 'thanhTien' => 700000 ) );

VHCP_Auth::dat_vai_tro( 'Admin', 'Admin' );
teq( 'đơn của NV POSH mang đơn vị POSH', 'POSH', VHCP_DonVi::cua_don( $_mp ) );
teq( 'đơn của NV K&H mang đơn vị K&H',   'K&H',  VHCP_DonVi::cua_don( $_mk ) );

/* Đơn cũ (lập trước khi có cột) có ô rỗng — phải hiểu là K&H, không phải "không thuộc đâu". */
global $wpdb;
$wpdb->update( VHCP_DB::t( 'don' ), array( 'don_vi' => '' ), array( 'ma_don' => $_mk ) );
teq( '🔴 đơn cũ ô rỗng vẫn là đơn K&H', 'K&H', VHCP_DonVi::cua_don( $_mk ) );

function _dv_ma_don_cua( $vai, $ten ) {
	VHCP_Auth::dat_vai_tro( $vai, $ten );
	$ra = array();
	foreach ( VHCP_Don::list_dons() as $d ) { $ra[] = $d['maDon']; }
	VHCP_Auth::dat_vai_tro( 'Admin', 'Admin' );
	return $ra;
}

/* ---- ai thấy gì ---- */
$_ds_ktp = _dv_ma_don_cua( 'Kế toán cá nhân', 'KT POSH' );
t( '🔴 kế toán POSH thấy đơn POSH',      in_array( $_mp, $_ds_ktp, true ), $_ds_ktp );
t( '🔴 kế toán POSH KHÔNG thấy đơn K&H', ! in_array( $_mk, $_ds_ktp, true ), $_ds_ktp );

$_ds_ktk = _dv_ma_don_cua( 'Kế toán cá nhân', 'KT Chỉ K&H' );
t( 'kế toán chỉ-K&H thấy đơn K&H (kể cả đơn cũ ô rỗng)', in_array( $_mk, $_ds_ktk, true ), $_ds_ktk );
t( 'kế toán chỉ-K&H KHÔNG thấy đơn POSH', ! in_array( $_mp, $_ds_ktk, true ), $_ds_ktk );

/* Kế toán cá nhân KHÔNG khai ô "Xem đơn vị" -> nhìn chung cả hai. Đây là bản nâng cấp cho một
   hệ đang chạy: 240 người, không ai có ô đó, nên "để trống" phải là "như cũ", không phải "mù". */
$_ds_ktc = _dv_ma_don_cua( 'Kế toán cá nhân', 'KT Cá Nhân' );
t( '🔴 kế toán để trống ô Xem đơn vị thì nhìn CHUNG cả hai',
	in_array( $_mp, $_ds_ktc, true ) && in_array( $_mk, $_ds_ktc, true ), $_ds_ktc );

$_ds_ad = _dv_ma_don_cua( 'Admin', 'Admin' );
t( 'Admin thấy cả hai', in_array( $_mp, $_ds_ad, true ) && in_array( $_mk, $_ds_ad, true ) );

/* ---- CỬA VÀO TỪNG ĐƠN, không chỉ danh sách ---- */
/* Lọc danh sách là để MẮT không thấy; chốt ở cửa là để TAY không với tới. Thiếu cái thứ hai
   thì gõ mã đơn lên thanh địa chỉ là qua, và cái thứ nhất chỉ còn là lớp sơn. */
VHCP_Auth::dat_vai_tro( 'Kế toán cá nhân', 'KT POSH' );
$_mo = VHCP_Don::get_don( $_mk );
t( '🔴 kế toán POSH KHÔNG mở được đơn K&H bằng mã', empty( $_mo['success'] ), $_mo );
t( 'và câu chối KHÔNG hé lộ là đơn ấy có thật',
	isset( $_mo['error'] ) && false === strpos( (string) $_mo['error'], 'đơn vị' ), $_mo );
t( 'kế toán POSH vẫn mở được đơn POSH', ! empty( VHCP_Don::get_don( $_mp )['success'] ) );

/* Sửa TỪNG DÒNG cũng phải chặn — không thì mở đơn thì không được mà sửa ruột nó thì được. */
VHCP_Auth::dat_vai_tro( 'Admin', 'Admin' );
$_lines_k = VHCP_Don::get_don( $_mk )['lines'];
$_dong_k  = $_lines_k[0]['id'];
VHCP_Auth::dat_vai_tro( 'Kế toán cá nhân', 'KT POSH' );
$_sua = VHCP_Don::update_line( $_dong_k, array( 'coso' => 'FARM PHAN THIẾT',
	'nhom' => 'Chi phí cơ sở', 'noiDung' => 'chen vào', 'soLuong' => 1, 'donGia' => 1, 'thanhTien' => 1 ) );
t( '🔴 kế toán POSH KHÔNG sửa được DÒNG của đơn K&H', empty( $_sua['success'] ), $_sua );
$_xoa = VHCP_Don::delete_line( $_dong_k );
t( 'và cũng không xoá được dòng ấy', empty( $_xoa['success'] ), $_xoa );

/* ---- duyệt: bên POSH là Quản lý POSH ----
   🔴 GỌI QUA ĐÚNG CÁI CỬA MÀ GIAO DIỆN ĐI: `VHCP_Api::handle()`.
      Chốt đơn vị cho nhóm hàm duyệt / cấp tiền / trả lại / tất toán nằm ở cửa API, không nằm
      trong từng hàm — xem `VHCP_DonVi::chan_theo_ham()` cho lý do. Gọi thẳng `VHCP_Don::…`
      trong bài kiểm là đi vòng qua đúng cái chốt đang cần kiểm, và phép thử sẽ xanh cho một
      thứ chưa bao giờ chạy. */
function _dv_api( $pin, $fn, $args ) {
	$dn = VHCP_Auth::login( (string) $pin );
	if ( empty( $dn['ok'] ) ) { return array( 'ok' => false, 'error' => 'không đăng nhập được: ' . $pin ); }
	$r = VHCP_API::handle( new WP_REST_Request( array(
		'fn' => $fn, 'args' => $args, 'token' => $dn['token'] ) ) );
	$d = $r->get_data();
	VHCP_Auth::dat_vai_tro( 'Admin', 'Admin' );
	return $d;
}
/**
 * ⚠️ CỬA API TRẢ HAI TẦNG "được/không": `ok` là tầng vận chuyển (đúng hàm, đúng vai, đúng đơn
 *    vị), `data.success` là tầng nghiệp vụ (đơn đã chốt, sai trạng thái…). Chỉ soi `ok` là một
 *    lệnh bị lõi chối vẫn đọc thành "chạy được" — và phép thử xanh cho thứ chưa bao giờ chạy.
 */
function _dv_ok( $d ) {
	return ! empty( $d['ok'] ) && ! empty( $d['data']['success'] );
}
function _dv_loi( $d ) {
	if ( ! empty( $d['error'] ) ) { return (string) $d['error']; }
	return isset( $d['data']['error'] ) ? (string) $d['data']['error'] : '';
}

VHCP_Auth::dat_vai_tro( 'Nhân viên', 'NV POSH' );
VHCP_Don::gui_duyet_tam_ung( $_mp );
VHCP_Auth::dat_vai_tro( 'Nhân viên', 'NV K&H' );
VHCP_Don::gui_duyet_tam_ung( $_mk );
VHCP_Auth::dat_vai_tro( 'Admin', 'Admin' );

$_dk_ql = _dv_api( '2005', 'duyetTamUng', array( $_mk, 'QL POSH' ) );
t( '🔴 Quản lý POSH KHÔNG duyệt được đơn K&H', ! _dv_ok( $_dk_ql ), $_dk_ql );
$_dp_ql = _dv_api( '2005', 'duyetTamUng', array( $_mp, 'QL POSH' ) );
t( '🔴 Quản lý POSH duyệt được đơn POSH', _dv_ok( $_dp_ql ), $_dp_ql );
$_dk_ad = _dv_api( '1111', 'duyetTamUng', array( $_mk, 'Admin' ) );
t( 'Admin vẫn duyệt được đơn K&H', _dv_ok( $_dk_ad ), $_dk_ad );

/* Bản "nhiều đơn một lượt" cũng phải gác — gói một đơn K&H lẫn vào danh sách là qua mặt được
   chốt đơn-lẻ nếu chốt chỉ soi tham số đầu tiên. */
$_nhieu = _dv_api( '2005', 'duyetTamUngNhieu', array( array( $_mp, $_mk ), 'QL POSH' ) );
t( '🔴 gói lẫn một đơn K&H vào lệnh duyệt nhiều thì CHỐI CẢ GÓI', ! _dv_ok( $_nhieu ), $_nhieu );

/* Cấp tiền cũng vậy — duyệt xong còn một cửa nữa, và tiền đi ra ở cửa ấy. */
$_cap = _dv_api( '2005', 'capTamUng', array( $_mk, 'QL POSH', 'Tiền mặt', '' ) );
t( '🔴 Quản lý POSH KHÔNG cấp được tiền cho đơn K&H', ! _dv_ok( $_cap ), $_cap );

/* ---- tìm đơn ---- */
/* 🔴 Ô TÌM PHẢI CHẠY ĐÃ. `tim_don()` viết `d.coso` trong khi bảng `don` chưa từng có cột ấy,
   nên câu SQL hỏng ở MỌI lượt gọi; wpdb nuốt lỗi và trả rỗng, thành ra ô tìm luôn báo "không
   tìm thấy đơn nào khớp" dù sổ có mấy trăm đơn. Không có gì đỏ, không có gì kêu.
   Anh Thắng 26/08: *"Lọc tìm kiếm chung theo loại chi phí lẻ anh chưa thấy"*. */
VHCP_Auth::dat_vai_tro( 'Admin', 'Admin' );
$_tim = VHCP_Don::tim_don( 'Đèn' );
t( '🔴 tìm đơn CHẠY ĐƯỢC (câu SQL không hỏng)', ! empty( $_tim['success'] ), $_tim );
t( 'và tìm ra cả hai đơn', count( $_tim['items'] ) >= 2, $_tim['items'] );
$_co_cs = false;
foreach ( $_tim['items'] as $x ) { if ( 'FARM PHAN THIẾT' === $x['coso'] ) { $_co_cs = true; } }
t( 'kết quả nói rõ đơn thuộc cơ sở nào (lấy từ dòng chi)', $_co_cs, $_tim['items'] );
t( 'lọc theo cơ sở cũng chạy', ! empty( VHCP_Don::tim_don( '', 'FARM PHAN THIẾT' )['items'] ) );
t( 'lọc theo loại chi phí cũng chạy', ! empty( VHCP_Don::tim_don( '', '', 'Chi phí cơ sở' )['items'] ) );

/* 🔴 Ô TÌM LỌC ĐƠN VỊ TRONG SQL, và SQL phải hiểu ô rỗng là nhà mặc định.
   `$_mk` ở trên đã bị đặt `don_vi = ''` để giả sổ cũ. Kế toán chỉ-K&H mà không tìm ra nó thì
   nghĩa là toàn bộ sổ cũ đã biến mất khỏi ô tìm của chính K&H — mà sổ cũ là gần như tất cả. */
/* 🔴 KẾT QUẢ TÌM PHẢI TRẢ VỀ DÒNG CHI KHỚP, KHÔNG CHỈ TÊN LOẠI.
   Anh Thắng 26/08/2026: *"chỗ này phải hiện hàng con dưa leo ra chứ, hiện tên đơn thì không
   biết được"*. Gõ "dưa leo" ra 9 đơn mà cột kết quả ghi "Chi phí NVL đồ ăn - Mua lẻ" ở cả 9
   dòng — đúng một cái tên, nhìn xong vẫn phải mở từng đơn ra xem. */
VHCP_Auth::dat_vai_tro( 'Admin', 'Admin' );
$_dd = VHCP_Don::create_don( 'T8/2026 (17/8-23/8/2026)', 'NV K&H' );
$_mdd = $_dd['maDon'];
foreach ( array( array( 'dưa leo', 60000 ), array( 'dưa chuột', 30000 ), array( 'thịt heo', 90000 ) ) as $_x ) {
	VHCP_Don::add_line( $_mdd, array( 'coso' => 'FARM PHAN THIẾT', 'ngay' => $today,
		'phanLoaiTT' => 'Thanh toán cá nhân', 'nhom' => 'Chi phí cơ sở', 'noiDung' => $_x[0],
		'soLuong' => 1, 'donGia' => $_x[1], 'thanhTien' => $_x[1] ) );
}
$_t_dl = VHCP_Don::tim_don( 'dưa leo' );
$_it = null;
foreach ( $_t_dl['items'] as $x ) { if ( $x['maDon'] === $_mdd ) { $_it = $x; } }
t( 'tìm ra đơn chứa dòng "dưa leo"', null !== $_it, $_t_dl['items'] );
t( '🔴 kết quả kèm DÒNG CHI khớp', ! empty( $_it['dong'] ), $_it );
teq( 'đúng một dòng khớp, không lôi cả đơn ra', 1, count( $_it['dong'] ) );
teq( 'và đúng cái dòng ấy', 'dưa leo', $_it['dong'][0]['noiDung'] );
teq( 'kèm số tiền của chính dòng ấy', 60000, VHCP_Util::num( $_it['dong'][0]['tien'] ) );
t( 'dòng KHÔNG khớp thì không lọt vào',
	'thịt heo' !== $_it['dong'][0]['noiDung'], $_it['dong'] );

/* Gõ một từ khớp NHIỀU dòng thì trả nhiều, và đếm đúng. */
$_t_dua = VHCP_Don::tim_don( 'dưa' );
$_it2 = null;
foreach ( $_t_dua['items'] as $x ) { if ( $x['maDon'] === $_mdd ) { $_it2 = $x; } }
teq( 'khớp hai dòng thì trả hai', 2, count( $_it2['dong'] ) );
teq( 'và số đếm nói đúng hai', 2, (int) $_it2['soDongKhop'] );

/* 🔴 CẮT BỚT THÌ PHẢI NÓI RA CÒN BAO NHIÊU. Cắt im lặng thì "5 dòng" trông y hệt "chỉ có 5
   dòng", và người tìm tin vào một con số thiếu. */
for ( $i = 0; $i < 8; $i++ ) {
	VHCP_Don::add_line( $_mdd, array( 'coso' => 'FARM PHAN THIẾT', 'ngay' => $today,
		'phanLoaiTT' => 'Thanh toán cá nhân', 'nhom' => 'Chi phí cơ sở', 'noiDung' => 'dưa số ' . $i,
		'soLuong' => 1, 'donGia' => 1000, 'thanhTien' => 1000 ) );
}
$_t_nhieu = VHCP_Don::tim_don( 'dưa' );
$_it3 = null;
foreach ( $_t_nhieu['items'] as $x ) { if ( $x['maDon'] === $_mdd ) { $_it3 = $x; } }
teq( 'trả tối đa 5 dòng', 5, count( $_it3['dong'] ) );
teq( '🔴 nhưng số đếm là SỐ THẬT, không phải số đã cắt', 10, (int) $_it3['soDongKhop'] );

/* Tìm theo kỳ / người lập thì không có dòng nào khớp — trả mảng rỗng, không trả bừa. */
$_t_ky = VHCP_Don::tim_don( 'NV K&H' );
$_it4 = null;
foreach ( $_t_ky['items'] as $x ) { if ( $x['maDon'] === $_mdd ) { $_it4 = $x; } }
t( 'tìm theo người lập vẫn ra đơn', null !== $_it4, $_t_ky['items'] );
teq( 'nhưng không dòng nào khớp thì trả rỗng', 0, count( $_it4['dong'] ) );

VHCP_Don::delete_don_admin( $_mdd );

VHCP_Auth::dat_vai_tro( 'Kế toán cá nhân', 'KT Chỉ K&H' );
$_tim_k = VHCP_Don::tim_don( 'Đèn' );
$_ma_k = array();
foreach ( $_tim_k['items'] as $x ) { $_ma_k[] = $x['maDon']; }
t( '🔴 ô tìm vẫn ra đơn cũ có ô đơn vị RỖNG', in_array( $_mk, $_ma_k, true ), $_ma_k );
t( 'và không ra đơn POSH', ! in_array( $_mp, $_ma_k, true ), $_ma_k );

VHCP_Auth::dat_vai_tro( 'Kế toán cá nhân', 'KT POSH' );
$_tim_p = VHCP_Don::tim_don( 'Đèn' );
$_ma_p = array();
foreach ( $_tim_p['items'] as $x ) { $_ma_p[] = $x['maDon']; }
t( '🔴 ô tìm của kế toán POSH KHÔNG trả đơn K&H', ! in_array( $_mk, $_ma_p, true ), $_ma_p );
t( 'nhưng vẫn trả đơn POSH', in_array( $_mp, $_ma_p, true ), $_ma_p );


/* ---- chuyển đơn / dòng chi sang đơn vị khác ----
   Anh Thắng 26/08: *"Kế toán Posh có thể sẽ đẩy đơn hoặc chi phí lẻ trong đơn cho kế toán cá
   nhân — thì khi chuyển qua nó sẽ tạo thành 1 đơn như nhân viên tạo bình thường."* */
VHCP_Auth::dat_vai_tro( 'Nhân viên', 'NV POSH' );
$_dc = VHCP_Don::create_don( 'T8/2026 (17/8-23/8/2026)', 'NV POSH' );
$_mc = $_dc['maDon'];
foreach ( array( array( 'Ghế POSH', 300000 ), array( 'Bàn POSH', 200000 ), array( 'Đèn bàn', 100000 ) ) as $_x ) {
	VHCP_Don::add_line( $_mc, array( 'coso' => 'FARM PHAN THIẾT', 'ngay' => $today,
		'phanLoaiTT' => 'Thanh toán cá nhân', 'nhom' => 'Chi phí cơ sở', 'noiDung' => $_x[0],
		'soLuong' => 1, 'donGia' => $_x[1], 'thanhTien' => $_x[1] ) );
}
VHCP_Auth::dat_vai_tro( 'Admin', 'Admin' );
$_dong_mc = array();
foreach ( VHCP_Don::get_don( $_mc )['lines'] as $_l ) { $_dong_mc[] = $_l['id']; }
teq( 'đơn nguồn có 3 dòng', 3, count( $_dong_mc ) );

/* Nhân viên KHÔNG được đẩy tiền sang sổ bên kia — chốt ở cửa API theo vai. */
$_nv_chuyen = _dv_api( '2002', 'chuyenDonVi', array( $_mc, array( $_dong_mc[0] ), 'K&H', 'KT Cá Nhân' ) );
t( '🔴 Nhân viên KHÔNG chuyển được đơn sang đơn vị khác', ! _dv_ok( $_nv_chuyen ), $_nv_chuyen );

/* Kế toán POSH đẩy MỘT dòng lẻ sang K&H. */
$_ch = _dv_api( '2004', 'chuyenDonVi', array( $_mc, array( $_dong_mc[0] ), 'K&H', 'KT Cá Nhân' ) );
t( '🔴 kế toán POSH đẩy được một dòng lẻ sang K&H', _dv_ok( $_ch ), $_ch );
$_kq = $_ch['data'];
t( 'trả về mã đơn mới', ! empty( $_kq['maDonMoi'] ), $_kq );
teq( 'đẩy đúng 1 dòng', 1, $_kq['soDong'] );
teq( 'đúng số tiền của dòng ấy', 300000, VHCP_Util::num( $_kq['soTien'] ) );
t( 'và nói rõ đơn gốc CHƯA rỗng', empty( $_kq['goc_rong'] ), $_kq );

$_mm = $_kq['maDonMoi'];
VHCP_Auth::dat_vai_tro( 'Admin', 'Admin' );
teq( '🔴 đơn mới thuộc đơn vị K&H', 'K&H', VHCP_DonVi::cua_don( $_mm ) );
teq( 'đơn mới ở trạng thái Nháp — đi lại từ đầu quy trình bên nhận',
	'Nháp', VHCP_Don::get_don( $_mm )['don']['trangThai'] );
teq( 'đơn mới đứng tên người nhận', 'KT Cá Nhân', VHCP_Don::get_don( $_mm )['don']['nguoiLap'] );
teq( 'đơn mới giữ NGUYÊN KỲ của đơn gốc — kẻo sổ hai bên lệch kỳ',
	VHCP_Don::get_don( $_mc )['don']['ky'], VHCP_Don::get_don( $_mm )['don']['ky'] );
teq( 'đơn mới có đúng 1 dòng', 1, count( VHCP_Don::get_don( $_mm )['lines'] ) );
teq( '🔴 đơn gốc còn 2 dòng — dòng đã chuyển KHÔNG nhân đôi',
	2, count( VHCP_Don::get_don( $_mc )['lines'] ) );

/* Cả hai bên đều còn vết: bên gửi ghi "đẩy đi", bên nhận ghi "nhận về". */
$_nk_goc = json_encode( VHCP_Don::nhat_ky_don( $_mc ), JSON_UNESCAPED_UNICODE );
$_nk_moi = json_encode( VHCP_Don::nhat_ky_don( $_mm ), JSON_UNESCAPED_UNICODE );
t( '🔴 đơn gốc có vết "đã đẩy đi"', false !== strpos( $_nk_goc, 'Chuyển sang đơn vị khác' ), $_nk_goc );
t( '🔴 đơn mới có vết "nhận về"', false !== strpos( $_nk_moi, 'Nhận từ đơn vị khác' ), $_nk_moi );
t( 'vết bên gửi trỏ tới đơn mới', false !== strpos( $_nk_goc, $_mm ), $_nk_goc );
t( 'vết bên nhận trỏ về đơn gốc', false !== strpos( $_nk_moi, $_mc ), $_nk_moi );

/* 🔴 ĐƠN VỊ CỦA ĐƠN MỚI LÀ ĐƠN VỊ ĐÍCH NGƯỜI CHUYỂN CHỌN, không phải nhà của người đứng tên.
   `create_don()` gán đơn vị theo NHÀ của người đứng tên — đúng cho đường lập đơn bình thường,
   sai cho đường này. Phép trên không bắt được chỗ đó vì "KT Cá Nhân" tình cờ cũng ở nhà K&H;
   nên chuyển thêm một lượt cho người ĐANG Ở POSH, khi ấy hai giá trị mới khác nhau. */
$_dx = VHCP_Don::create_don( 'T8/2026 (17/8-23/8/2026)', 'NV POSH' );
$_mx = $_dx['maDon'];
VHCP_Don::add_line( $_mx, array( 'coso' => 'FARM PHAN THIẾT', 'ngay' => $today,
	'phanLoaiTT' => 'Thanh toán cá nhân', 'nhom' => 'Chi phí cơ sở', 'noiDung' => 'Kiểm đích',
	'soLuong' => 1, 'donGia' => 90000, 'thanhTien' => 90000 ) );
$_chx = _dv_api( '2004', 'chuyenDonVi', array( $_mx, array(), 'K&H', 'NV POSH' ) );
t( 'chuyển được cho người đang ở POSH', _dv_ok( $_chx ), $_chx );
VHCP_Auth::dat_vai_tro( 'Admin', 'Admin' );
teq( '🔴 đơn mới mang ĐƠN VỊ ĐÍCH, không mang nhà của người đứng tên',
	'K&H', VHCP_DonVi::cua_don( $_chx['data']['maDonMoi'] ) );
teq( 'nhưng vẫn đứng tên đúng người nhận', 'NV POSH',
	VHCP_Don::get_don( $_chx['data']['maDonMoi'] )['don']['nguoiLap'] );
VHCP_Don::delete_don_admin( $_chx['data']['maDonMoi'] );
VHCP_Don::delete_don_admin( $_mx );

/* Chuyển sang chính đơn vị của mình thì vô nghĩa — chối. */
$_tu = _dv_api( '2004', 'chuyenDonVi', array( $_mc, array(), 'POSH', 'KT POSH' ) );
t( 'chuyển sang chính đơn vị của mình thì chối', ! _dv_ok( $_tu ), _dv_loi( $_tu ) );
/* Đơn vị chưa ai khai thì chối — chuyển vào hư không là mất dấu đơn. */
$_la = _dv_api( '2004', 'chuyenDonVi', array( $_mc, array(), 'ĐƠN VỊ MA', 'KT Cá Nhân' ) );
t( 'đơn vị chưa khai thì chối, không chuyển vào hư không', ! _dv_ok( $_la ), _dv_loi( $_la ) );

/* Đẩy NỐT cả đơn: ids rỗng = cả đơn. */
$_ch2 = _dv_api( '2004', 'chuyenDonVi', array( $_mc, array(), 'K&H', 'KT Cá Nhân' ) );
t( 'đẩy được CẢ ĐƠN khi không chọn dòng nào', _dv_ok( $_ch2 ), $_ch2 );
teq( 'đẩy nốt 2 dòng còn lại', 2, $_ch2['data']['soDong'] );
t( '🔴 và nói rõ đơn gốc nay đã RỖNG (để màn mời xoá, không tự xoá)',
	! empty( $_ch2['data']['goc_rong'] ), $_ch2['data'] );
VHCP_Auth::dat_vai_tro( 'Admin', 'Admin' );
teq( 'đơn gốc còn 0 dòng', 0, count( VHCP_Don::get_don( $_mc )['lines'] ) );

/* Đơn đã chốt sổ thì KHÔNG rút dòng ra được — số quyết toán đã chốt sẽ không còn khớp ruột. */
$_dq = VHCP_Don::create_don( 'T8/2026 (17/8-23/8/2026)', 'NV POSH' );
$_mq = $_dq['maDon'];
VHCP_Don::add_line( $_mq, array( 'coso' => 'FARM PHAN THIẾT', 'ngay' => $today,
	'phanLoaiTT' => 'Thanh toán cá nhân', 'nhom' => 'Chi phí cơ sở', 'noiDung' => 'Đã chốt',
	'soLuong' => 1, 'donGia' => 50000, 'thanhTien' => 50000 ) );
global $wpdb;
$wpdb->update( VHCP_DB::t( 'don' ), array( 'trang_thai' => 'Đã quyết toán' ), array( 'ma_don' => $_mq ) );
$_chot = _dv_api( '2004', 'chuyenDonVi', array( $_mq, array(), 'K&H', 'KT Cá Nhân' ) );
t( '🔴 đơn đã quyết toán thì KHÔNG rút dòng ra chuyển được', ! _dv_ok( $_chot ), _dv_loi( $_chot ) );

VHCP_Auth::dat_vai_tro( 'Admin', 'Admin' );
foreach ( array( $_mc, $_mm, $_mq ) as $_z ) { VHCP_Don::delete_don_admin( $_z ); }
foreach ( VHCP_Don::list_dons() as $_z ) {
	if ( 'KT Cá Nhân' === $_z['nguoiLap'] ) { VHCP_Don::delete_don_admin( $_z['maDon'] ); }
}

VHCP_Auth::dat_vai_tro( 'Admin', 'Admin' );
VHCP_Don::delete_don_admin( $_mp );
VHCP_Don::delete_don_admin( $_mk );
VHCP_Cfg::write( VHCP_Cfg::USER, array( array( 'Admin', '1111', 'Admin', '', '', '', '', '', '' ) ) );
VHCP_Auth::dat_vai_tro( $_vt_dv, 'Admin' );

echo 'ĐẠT: ' . $GLOBALS['T_OK'] . ' phép thử' . "\n";
if ( count( $GLOBALS['T_NG'] ) ) {
	echo 'HỎNG: ' . count( $GLOBALS['T_NG'] ) . "\n";
	foreach ( $GLOBALS['T_NG'] as $x ) { echo '  ✗ ' . $x . "\n"; }
	exit( 1 );
}
echo "Tất cả phép thử đều đạt.\n";
