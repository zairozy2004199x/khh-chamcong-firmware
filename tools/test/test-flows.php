<?php
/**
 * Kiểm nghiệm logic plugin Vận Hành Chi Phí (chạy bằng PHP CLI, không cần WordPress).
 *   php tools/test/test-flows.php
 */

require_once __DIR__ . '/wp-stub.php';
vhcp_test_boot( dirname( dirname( __DIR__ ) ) . '/wordpress/vhcp-chi-phi' );

$GLOBALS['T_OK'] = 0;
$GLOBALS['T_NG'] = array();

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
$l3 = VHCP_Don::add_line( $ma, array( 'coso' => 'TÀU TÂN PHÚ', 'ngay' => $today, 'phanLoaiTT' => 'Nhà cung cấp', 'doiTuong' => 'CTY ABC', 'nhom' => 'SP Đồ uống - NCC', 'noiDung' => 'Nước ngọt', 'soLuong' => 20, 'donGia' => 15000, 'thanhTien' => 300000, 'thueSuat' => 8 ) );
teq( 'dòng 1..3 đều là hạng mục xin', 0, $l3['phatSinh'] );

$g = VHCP_Don::get_don( $ma );
teq( 'tạm ứng FARM = 1.500.000', 1500000, $g['tamUng']['FARM PHAN THIẾT'] );
teq( 'tạm ứng TÀU TÂN PHÚ = 300.000', 300000, $g['tamUng']['TÀU TÂN PHÚ'] );
teq( 'tổng tạm ứng cục = 1.800.000', 1800000, $g['tongCN']['tamUng'] );
teq( 'đã chi cá nhân = 1.500.000', 1500000, $g['tongCN']['thucChi'] );
teq( 'đã chi NCC = 300.000', 300000, $g['tongNCC']['thucChi'] );
teq( 'tiền thuế dòng NCC = 24.000', 24000, $g['lines'][2]['tienThue'] );
teq( 'ngày dòng hiện dd/MM/yyyy', $today, $g['lines'][0]['ngay'] );
t( 'thực mua để trống', $g['lines'][0]['thucMua'] === '' );

// dự phòng + bù trừ khi còn Nháp
t( 'set dự phòng khi Nháp', ! empty( VHCP_Don::set_tu_extra( $ma, 200000, -100000 )['success'] ) );
$g = VHCP_Don::get_don( $ma );
teq( 'tạm ứng cục có dự phòng + bù trừ', 1900000, $g['tongCN']['tamUng'] );

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
t( 'không xóa được hạng mục xin sau khi cấp', empty( VHCP_Don::delete_line( $l1['id'] )['success'] ) );
t( 'xóa được dòng phát sinh', ! empty( VHCP_Don::delete_line( $l4['id'] )['success'] ) );
$l4 = VHCP_Don::add_line( $ma, array( 'coso' => 'FARM PHAN THIẾT', 'ngay' => $today, 'phanLoaiTT' => 'Thanh toán cá nhân', 'nhom' => 'Phát sinh', 'noiDung' => 'Mua thêm đá', 'thanhTien' => 50000 ) );

// nhập thực chi
t( 'nhập thực chi dòng 1', ! empty( VHCP_Don::set_line_thuc_mua( $l1['id'], 1150000, 'Nguyễn Văn A' )['success'] ) );
VHCP_Don::set_line_thuc_mua( $l2['id'], 300000, 'Nguyễn Văn A' );
VHCP_Don::set_line_thuc_mua( $l3['id'], 300000, 'Nguyễn Văn A' );
VHCP_Don::set_line_thuc_mua( $l4['id'], 50000, 'Nguyễn Văn A' );
$g = VHCP_Don::get_don( $ma );
teq( 'đã chi cá nhân sau thực chi', 1500000, $g['tongCN']['thucChi'] );   // 1.150.000 + 300.000 + 50.000
teq( 'tạm ứng cục giữ nguyên', 1900000, $g['tongCN']['tamUng'] );
teq( 'chênh lệch = thừa 400.000', 400000, $g['tongCN']['chenhLech'] );

t( 'gửi quyết toán', ! empty( VHCP_Don::gui_quyet_toan( $ma )['success'] ) );
$before = VHCP_Don::don_row( $ma )['ghi_chu'];
VHCP_Don::set_line_thuc_mua( $l1['id'], 1100000, 'Lê Kế Toán' );
t( 'kế toán sửa số khi Chờ quyết toán -> gắn cờ [KT sửa]', strpos( VHCP_Don::don_row( $ma )['ghi_chu'], '[KT sửa]' ) !== false, VHCP_Don::don_row( $ma )['ghi_chu'] );

$g = VHCP_Don::get_don( $ma );
teq( 'chênh lệch sau khi KT sửa', 450000, $g['tongCN']['chenhLech'] );
$b = VHCP_Don::xac_nhan_qt_cn_nhieu( array( $ma ), 'Lê Kế Toán' );
t( 'duyệt quyết toán theo lô', ! empty( $b['success'] ) && $b['done'] === 1, $b );
$row = VHCP_Don::don_row( $ma );
teq( 'trạng thái sau quyết toán', 'Đã quyết toán', $row['trang_thai'] );
teq( 'xử lý = NV trả lại', 'NV trả lại', $row['xu_ly'] );
teq( 'chênh lệch lưu vào đơn', 450000, (float) $row['chenh_lech_qt'] );

// danh sách đơn
$dons = VHCP_Don::list_dons();
teq( 'danh sách có 1 đơn', 1, count( $dons ) );
teq( 'cơ sở gom trên danh sách', 'FARM PHAN THIẾT, TÀU TÂN PHÚ', $dons[0]['coso'] );
teq( 'thực chi cá nhân trên danh sách', 1450000, $dons[0]['thucChiCN'] );
teq( 'thực chi NCC trên danh sách', 300000, $dons[0]['thucChiNCC'] );

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
teq( 'bù trừ vào báo cáo', -100000, $fr['totals']['buTru'] );
teq( 'số đơn', 1, $fr['totals']['soDon'] );
$fr2 = VHCP_Report::finance( array( 'coso' => 'TÀU TÂN PHÚ' ) );
teq( 'lọc theo cơ sở', 300000, $fr2['totals']['thucTe'] );

// ---------------------------------------------------------------- 5. xuất MISA đơn vận hành
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
teq( 'MISA: 1 đơn', 1, $ex['sodon'] );
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

teq( 'MISA lọc NCC: chưa duyệt NCC thì không xuất', 0, VHCP_Misa::export_misa( 'all', 'chuaxuat', 'ncc' )['count'] );
t( 'kế toán NCC duyệt độc lập', ! empty( VHCP_Don::xac_nhan_quyet_toan_ncc( $ma, 'Phạm KT NCC' )['success'] ) );
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

t( 'chốt đã xuất', ! empty( VHCP_Misa::mark_exported( array( $ma ), 'all' )['success'] ) );
teq( 'trạng thái sau chốt xuất', 'Đã xuất MISA', VHCP_Don::don_row( $ma )['trang_thai'] );
teq( 'xuất lại lần 2 không còn đơn nào', 0, VHCP_Misa::export_misa( 'all', 'chuaxuat', 'all' )['count'] );
teq( 'lọc mode đã xuất thì thấy lại', 4, VHCP_Misa::export_misa( 'all', 'daxuat', 'all' )['count'] );

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
teq( 'dự án: cần tạm ứng (dự toán hình thức Tạm ứng)', 10000000, $gd['canTamUng'] );
teq( 'dự án: trả trực tiếp (dự toán)', 2000000, $gd['traTrucTiep'] );
teq( 'dự án: thực tế tạm ứng', 9000000, $gd['ttTamUng'] );
teq( 'dự án: thực tế trực tiếp', 2500000, $gd['ttTrucTiep'] );
teq( 'dự án: trực tiếp có VAT', 2500000, $gd['ttTrucTiepVAT'] );
teq( 'mục con thừa hưởng hình thức chi của cha', 'Tạm ứng', $gd['lines'][1]['hinhThuc'] );

t( 'gửi kế toán duyệt', ! empty( VHCP_DuAn::submit( $md )['success'] ) );
t( 'đang chờ duyệt thì khóa nhập', empty( VHCP_DuAn::add_line( $md, array( 'noiDung' => 'X' ) )['success'] ) );
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

// ---------------------------------------------------------------- 11. nhật ký
VHCP_Log::log_action( array( 'actor' => 'Admin', 'role' => 'Admin', 'action' => 'Thử nghiệm', 'target' => $ma, 'detail' => 'ghi chú' ) );
$lg = VHCP_Log::get_log( array( 'limit' => 10 ) );
teq( 'nhật ký ghi được', 1, count( $lg['items'] ) );
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
	'dayChoKeToan',
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
$chua_co = array();
foreach ( $gas as $fn ) { if ( ! isset( $map[ $fn ] ) ) { $chua_co[] = $fn; } }
teq( 'đủ 100% hàm của app Apps Script cũ', array(), $chua_co );
teq( 'số hàm cũ đã port', 92, count( $gas ) );

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

// ---------------------------------------------------------------- kết quả
echo "\n";
echo 'ĐẠT: ' . $GLOBALS['T_OK'] . ' phép thử' . "\n";
if ( count( $GLOBALS['T_NG'] ) ) {
	echo 'HỎNG: ' . count( $GLOBALS['T_NG'] ) . "\n";
	foreach ( $GLOBALS['T_NG'] as $x ) { echo '  ✗ ' . $x . "\n"; }
	exit( 1 );
}
echo "Tất cả phép thử đều đạt.\n";
