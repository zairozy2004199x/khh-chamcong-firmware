<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * TRỌN LUỒNG CHI PHÍ CƠ SỞ — MỘT ĐƠN ĐI TỪ ĐẦU TỚI CUỐI, VỚI ĐÚNG NHỮNG VAI THẬT.
 *
 * Anh Thắng 12/09/2026: *"Chạy test lại quy trình cơ sở, xem có sự sai lệch nào không"* — sau
 * lượt đổi lớn 1.146.0 (gộp hai cột đơn vị · bó Quản lý theo cơ sở · bỏ cột TK Có).
 *
 * =============================================================================================
 * 🔴 BÀI NÀY CHẠY LUỒNG, KHÔNG CHẠY HÀM LẺ. Mỗi bài kiểm khác soi một mảnh; mảnh nào cũng xanh
 *    mà ghép lại vẫn lệch được — đúng chỗ 1.146.0 dễ gãy nhất là chỗ NỐI giữa các mảnh (danh
 *    sách lọc một kiểu, cửa mở đơn lọc kiểu khác, sổ chi phí lại không lọc gì).
 *
 * 🔴 ĐĂNG NHẬP PHẢI GIỐNG CỔNG API THẬT. `dat_vai_tro()` nhận cơ sở ở THAM SỐ THỨ BA; quên
 *    truyền là `coso_ds()` rỗng và MỌI chốt phạm vi xanh oan. Ngoài đời `VHCP_Auth::
 *    user_by_token()` đọc lại vai · cơ sở · bộ phận từ bảng người dùng mỗi lượt gọi, nên hàm
 *    `lam()` dưới đây cũng tra đúng như thế. Đã mắc đúng cái bẫy này lượt soi đầu.
 *
 * 🔴 CHỐT CƠ SỞ CỦA SỔ CHI PHÍ TỪNG THIẾU HẲN — soi ra bằng chính bài này. `$scope` trong
 *    `list_chi()` đọc từ tham số `coso_scope` do MÀN gửi lên, tức là Ô LỌC chứ không phải hàng
 *    rào: gọi thẳng cổng API mà không gửi tham số ấy thì nhận cả sổ của mọi cơ sở. Phần 6 canh
 *    đúng chỗ đó, và canh bằng cách gọi TRẦN — không truyền `coso_scope`.
 *
 * Chạy: php tools/test/kiem-luong-coso-tron-ven.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : var_export( $them, true ) ) ) : '' );
}
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . var_export( $mong, true ) . ')', $mong === $thuc, $thuc ); }

/** Đăng nhập như cổng API: vai + CƠ SỞ + bộ phận tra lại từ bảng người dùng. */
function lam( $vai, $ten ) {
	$cs = '';
	foreach ( VHCP_Cfg::get_users() as $u ) {
		if ( trim( (string) $u['ten'] ) === $ten ) { $cs = (string) $u['coso']; break; }
	}
	VHCP_Auth::dat_vai_tro( $vai, $ten, $cs );
}

VHCP_Cfg::seed();
VHCP_Cfg::append( VHCP_Cfg::COSO, array( 'FARM PHAN THIẾT', 'FPT', 'KVC MN',  '', '', '' ) );
VHCP_Cfg::append( VHCP_Cfg::COSO, array( 'FARM NHA TRANG',  'FNT', 'KVC MN',  '', '', '' ) );
VHCP_Cfg::append( VHCP_Cfg::COSO, array( 'TÀU TẤN PHÚ',     'TTP', 'KVC MN',  '', '', '' ) );
VHCP_Cfg::append( VHCP_Cfg::COSO, array( 'POSH SÀI GÒN',    'PSG', 'POSH MN', '', '', 'POSH' ) );
/* Cột: ten | pin | vai | coso | tkCo | maDt | boPhan | donVi | (cột 9 cũ, không còn ai đọc) */
VHCP_Cfg::write( VHCP_Cfg::USER, array(
	array( 'Sếp',      '1000', 'Admin',           '',                                '', '', '', 'K&H',  '' ),
	array( 'Chị Hòa',  '4444', 'Quản lý',         'FARM PHAN THIẾT, FARM NHA TRANG', '', '', '', 'K&H',  '' ),
	array( 'Chị Toàn', '4445', 'Quản lý',         '',                                '', '', '', 'K&H',  '' ),
	array( 'Bin',      '6353', 'Nhân viên',       'FARM PHAN THIẾT',                 '', '', '', 'K&H',  '' ),
	array( 'Thịnh',    '4455', 'Nhân viên',       'FARM NHA TRANG',                  '', '', '', 'K&H',  '' ),
	array( 'Truyền',   '5922', 'Nhân viên',       'TÀU TẤN PHÚ',                     '', '', '', 'K&H',  '' ),
	array( 'Chị Nhân', '2222', 'Kế toán cá nhân', '',                                '', '', '', 'K&H',  '' ),
	array( 'NV POSH',  '7777', 'Nhân viên',       'POSH SÀI GÒN',                    '', '', '', 'POSH', '' ),
	/* Nhân viên KHÔNG khai cơ sở nào — ô Cơ sở để trống. Hai vai hiểu ô trống ngược nhau, và
	   phần 4b canh đúng chỗ ấy. */
	array( 'Khôi',     '8888', 'Nhân viên',       '',                                '', '', '', 'K&H',  '' ),
) );
VHCP_Cfg::clear_cache();

$KY    = 'T9/2026 (7/9-13/9/2026)';
$today = VHCP_Util::today_sql();

/* ═══ 1. NHÂN VIÊN LẬP ĐƠN TUẦN ══════════════════════════════════════════════════════ */
lam( 'Nhân viên', 'Bin' );
$r = VHCP_Don::tao_don_moi( $KY, 'Bin' );
t( 'Bin lập được đơn tuần', ! empty( $r['success'] ), $r );
$D = (string) $r['maDon'];
teq( 'đơn mang đơn vị của NGƯỜI LẬP, đóng dấu một lần', 'K&H', VHCP_DonVi::cua_don( $D ) );

foreach ( array( array( 'Gas 12kg', 2, 450000 ), array( 'Nước rửa chén', 10, 35000 ) ) as $x ) {
	$a = VHCP_Don::add_line( $D, array(
		'coso' => 'FARM PHAN THIẾT', 'ngay' => $today, 'phanLoaiTT' => 'Thanh toán cá nhân',
		'nhom' => 'Chi phí cơ sở', 'noiDung' => $x[0],
		'soLuong' => $x[1], 'donGia' => $x[2], 'thanhTien' => $x[1] * $x[2] ) );
	t( 'thêm dòng "' . $x[0] . '"', ! empty( $a['success'] ), $a );
}
$g = VHCP_Don::get_don( $D );
teq( 'tổng dự toán = 1.250.000', 1250000.0,
	array_sum( array_map( function ( $l ) { return (float) $l['thanhTien']; }, $g['lines'] ) ) );

/* ═══ 2. AI THẤY ĐƠN NÀY — cơ cấu anh Thắng vạch 12/09/2026 ══════════════════════════ */
function thay( $vai, $ten, $D ) {
	lam( $vai, $ten );
	foreach ( VHCP_Don::list_dons() as $d ) { if ( $d['maDon'] === $D ) { return true; } }
	return false;
}
t( 'Bin (người lập) thấy đơn của mình',                          thay( 'Nhân viên', 'Bin', $D ) );
t( '🔴 Thịnh (nhân viên cơ sở KHÁC) KHÔNG thấy',               ! thay( 'Nhân viên', 'Thịnh', $D ) );
t( '🔴 Truyền (nhân viên cơ sở KHÁC) KHÔNG thấy',              ! thay( 'Nhân viên', 'Truyền', $D ) );
t( '🔴 Chị Hòa (quản lý CÓ khai cơ sở ấy) thấy',                 thay( 'Quản lý', 'Chị Hòa', $D ) );
t( '🔴 Chị Toàn (quản lý KHÔNG khai cơ sở nào) trông cả đơn vị', thay( 'Quản lý', 'Chị Toàn', $D ) );
t( 'kế toán thấy',                                               thay( 'Kế toán cá nhân', 'Chị Nhân', $D ) );
t( 'Sếp thấy',                                                   thay( 'Admin', 'Sếp', $D ) );
t( '🔴 NV POSH (nhà khác) KHÔNG thấy',                         ! thay( 'Nhân viên', 'NV POSH', $D ) );

/* ═══ 3. CỬA MỞ ĐƠN PHẢI KHỚP ĐÚNG BẰNG DANH SÁCH ═══════════════════════════════════
 * Thấy trong danh sách mà bấm vào lại bị chối thì tính năng coi như không có — và người dùng
 * sẽ tưởng hệ thống hỏng chứ không nghĩ là hai chốt khai khác nhau. */
function mo( $vai, $ten, $D ) { lam( $vai, $ten ); $g = VHCP_Don::get_don( $D ); return ! empty( $g['success'] ); }
t( 'Bin mở được',                       mo( 'Nhân viên', 'Bin', $D ) );
t( '🔴 Thịnh KHÔNG mở được',          ! mo( 'Nhân viên', 'Thịnh', $D ) );
t( '🔴 Chị Hòa mở được',                mo( 'Quản lý', 'Chị Hòa', $D ) );
t( '🔴 NV POSH KHÔNG mở được',        ! mo( 'Nhân viên', 'NV POSH', $D ) );

/* ═══ 4. QUẢN LÝ NGOÀI PHẠM VI KHÔNG SỬA ĐƯỢC (mới 1.146.0) ═════════════════════════
 * *"Quản Lý — chỉ xem, NHẬP DỮ LIỆU được bộ phận cơ sở mình quản lý"*. Trước bản này chốt
 * "đơn của mình" bỏ qua mọi vai không phải Nhân viên, nên quản lý khai đúng ba cơ sở của mình
 * vẫn sửa được đơn của cơ sở bất kỳ. */
lam( 'Nhân viên', 'Truyền' );
$D2 = (string) VHCP_Don::tao_don_moi( $KY, 'Truyền' )['maDon'];
VHCP_Don::add_line( $D2, array( 'coso' => 'TÀU TẤN PHÚ', 'ngay' => $today,
	'phanLoaiTT' => 'Thanh toán cá nhân', 'nhom' => 'Chi phí cơ sở', 'noiDung' => 'Đá lạnh',
	'soLuong' => 1, 'donGia' => 200000, 'thanhTien' => 200000 ) );
t( '🔴 Chị Hòa KHÔNG thấy đơn của cơ sở ngoài phạm vi mình', ! thay( 'Quản lý', 'Chị Hòa', $D2 ) );
lam( 'Quản lý', 'Chị Hòa' );
$chen = VHCP_Don::add_line( $D2, array( 'coso' => 'TÀU TẤN PHÚ', 'ngay' => $today,
	'phanLoaiTT' => 'Thanh toán cá nhân', 'nhom' => 'Chi phí cơ sở', 'noiDung' => 'Chen ngang',
	'soLuong' => 1, 'donGia' => 1, 'thanhTien' => 1 ) );
t( '🔴 và KHÔNG thêm được dòng vào đơn ấy', empty( $chen['success'] ), $chen );
t( 'Chị Toàn (quản lý cả đơn vị) thì thấy',  thay( 'Quản lý', 'Chị Toàn', $D2 ) );

/* ═══ 4b. HAI VẾ CÒN LẠI CỦA LUẬT PHẠM VI ═══════════════════════════════════════════
 * Hai phép dưới đây thêm sau lượt phá thử 12/09/2026 — đục bỏ từng vế mà cả bài vẫn xanh,
 * nghĩa là hai vế ấy chưa có ai canh.
 *
 * 🔴 VẾ "ĐƠN CỦA MÌNH" phải giữ cho cả hai vai. Bỏ đi là người lập đơn cho một cơ sở vừa bị
 *    gỡ khỏi danh sách phụ trách mất luôn chính cái đơn mình đang làm dở — mất trong im lặng,
 *    vì họ vẫn đăng nhập được và vẫn thấy những đơn khác. */
lam( 'Nhân viên', 'Bin' );
$D3 = (string) VHCP_Don::tao_don_moi( $KY, 'Bin' )['maDon'];
VHCP_Don::add_line( $D3, array( 'coso' => 'TÀU TẤN PHÚ', 'ngay' => $today,
	'phanLoaiTT' => 'Thanh toán cá nhân', 'nhom' => 'Chi phí cơ sở', 'noiDung' => 'Bin mua hộ TÀU',
	'soLuong' => 1, 'donGia' => 50000, 'thanhTien' => 50000 ) );
t( '🔴 Bin vẫn thấy đơn DO CHÍNH MÌNH lập, dù cơ sở ngoài phạm vi', thay( 'Nhân viên', 'Bin', $D3 ) );
t( '   và vẫn mở được nó',                                          mo( 'Nhân viên', 'Bin', $D3 ) );
t( '   còn Thịnh thì không',                                      ! thay( 'Nhân viên', 'Thịnh', $D3 ) );

/* 🔴 Ô CƠ SỞ RỖNG: NHÂN VIÊN BÓ CHẶT, QUẢN LÝ MỞ. Cùng một ô trống, hai nghĩa ngược nhau —
 *    vì hai vai khai nó theo hai ý khác nhau. Hiểu "rỗng = tất cả" cho nhân viên là người quên
 *    khai đọc được sổ cả công ty mà không ai nhận ra. */
t( '🔴 Khôi (nhân viên, ô Cơ sở TRỐNG) KHÔNG thấy đơn của Bin', ! thay( 'Nhân viên', 'Khôi', $D ) );
lam( 'Nhân viên', 'Khôi' );
$D4 = (string) VHCP_Don::tao_don_moi( $KY, 'Khôi' )['maDon'];
t( '   nhưng vẫn thấy đơn của chính mình', thay( 'Nhân viên', 'Khôi', $D4 ) );
t( '🔴 đối chứng — Chị Toàn (QUẢN LÝ, ô Cơ sở TRỐNG) thì thấy đơn của Bin', thay( 'Quản lý', 'Chị Toàn', $D ) );

/* ═══ 5. LUỒNG TIỀN: XIN → DUYỆT → CẤP → THỰC CHI → QUYẾT TOÁN ══════════════════════ */
lam( 'Nhân viên', 'Bin' );
t( 'Bin gửi xin tạm ứng', ! empty( VHCP_Don::gui_duyet_tam_ung( $D )['success'] ) );
teq( '   → Chờ duyệt tạm ứng', 'Chờ duyệt tạm ứng', VHCP_Don::get_don( $D )['don']['trangThai'] );

lam( 'Quản lý', 'Chị Hòa' );
t( 'Quản lý duyệt', ! empty( VHCP_Don::duyet_tam_ung( $D, 'Chị Hòa', 1250000 )['success'] ) );
teq( '   → Chờ cấp tạm ứng', 'Chờ cấp tạm ứng', VHCP_Don::get_don( $D )['don']['trangThai'] );

lam( 'Kế toán cá nhân', 'Chị Nhân' );
t( 'Kế toán cấp tiền', ! empty( VHCP_Don::cap_tam_ung( $D, 'Chị Nhân' )['success'] ) );
$gd = VHCP_Don::get_don( $D )['don'];
teq( '   → Đã cấp tạm ứng',      'Đã cấp tạm ứng', $gd['trangThai'] );
teq( '   số tạm ứng = 1.250.000', 1250000.0, (float) $gd['tamUngDuyet'] );

lam( 'Nhân viên', 'Bin' );
$g   = VHCP_Don::get_don( $D );
$ids = array(); foreach ( $g['lines'] as $l ) { $ids[] = (string) $l['id']; }
foreach ( array( 880000, 340000 ) as $i => $so ) {   // thực chi thấp hơn -> thừa 30.000
	t( 'nhập thực chi dòng ' . ( $i + 1 ), ! empty( VHCP_Don::set_line_thuc_mua( $ids[ $i ], $so, 'Bin' )['success'] ) );
}
$g = VHCP_Don::get_don( $D );
teq( 'tổng thực chi = 1.220.000', 1220000.0,
	array_sum( array_map( function ( $l ) { return (float) $l['thucMua']; }, $g['lines'] ) ) );

t( 'Bin gửi quyết toán', ! empty( VHCP_Don::gui_quyet_toan( $D )['success'] ) );
teq( '   → Chờ quyết toán', 'Chờ quyết toán', VHCP_Don::get_don( $D )['don']['trangThai'] );

lam( 'Kế toán cá nhân', 'Chị Nhân' );
t( 'kế toán chốt quyết toán', ! empty( VHCP_Don::xac_nhan_quyet_toan_cn( $D, 'Chị Nhân', 'Hoàn tiền', 30000 )['success'] ) );
teq( '   → Đã quyết toán', 'Đã quyết toán', VHCP_Don::get_don( $D )['don']['trangThai'] );

/* ═══ 6. SỔ CHI PHÍ CŨNG PHẢI LỌC THEO CƠ SỞ ════════════════════════════════════════
 * 🔴 GỌI TRẦN, KHÔNG TRUYỀN `coso_scope`. Tham số ấy là Ô LỌC của màn, không phải hàng rào —
 *    truyền nó vào đây là bài kiểm tự lọc hộ rồi khen mã đã lọc. */
VHCP_Auth::dat_vai_tro( 'Admin', 'Sếp', '' );
VHCP_SoChi::add( array( 'ngay' => $today, 'coso' => 'FARM PHAN THIẾT', 'loai' => 'Chi phí cơ sở',
	'noiDung' => 'Gas sổ phẳng', 'soTien' => 900000, 'ky' => $KY ), 'Bin' );
VHCP_SoChi::add( array( 'ngay' => $today, 'coso' => 'TÀU TẤN PHÚ', 'loai' => 'Chi phí cơ sở',
	'noiDung' => 'Đá lạnh sổ phẳng', 'soTien' => 200000, 'ky' => $KY ), 'Truyền' );

function so_chi( $vai, $ten ) {
	lam( $vai, $ten );
	$ra = array();
	foreach ( (array) VHCP_SoChi::list_chi( array() )['items'] as $x ) { $ra[] = (string) $x['noiDung']; }
	sort( $ra );
	return $ra;
}
teq( '🔴 Bin chỉ thấy dòng sổ của cơ sở mình',    array( 'Gas sổ phẳng' ),      so_chi( 'Nhân viên', 'Bin' ) );
teq( '🔴 Truyền chỉ thấy dòng sổ của cơ sở mình', array( 'Đá lạnh sổ phẳng' ), so_chi( 'Nhân viên', 'Truyền' ) );
teq( '🔴 Chị Hòa (quản lý FARM) không thấy dòng TÀU', array( 'Gas sổ phẳng' ), so_chi( 'Quản lý', 'Chị Hòa' ) );
teq( 'Sếp thấy cả hai', array( 'Gas sổ phẳng', 'Đá lạnh sổ phẳng' ), so_chi( 'Admin', 'Sếp' ) );

/* Thịnh không phụ trách cơ sở nào trong hai dòng trên, và cũng không nhập dòng nào. */
teq( '🔴 Thịnh không thấy dòng nào',     array(), so_chi( 'Nhân viên', 'Thịnh' ) );
teq( '🔴 Khôi (ô Cơ sở trống) cũng không', array(), so_chi( 'Nhân viên', 'Khôi' ) );
teq( '🔴 NV POSH (nhà khác) cũng không', array(), so_chi( 'Nhân viên', 'NV POSH' ) );
/* ⚠️ Ba phép trên phải đứng TRƯỚC dòng vô chủ thêm ở khối dưới — sau đó chúng không còn rỗng. */
/* 🔴 DÒNG CHƯA KHAI CƠ SỞ THÌ AI CŨNG ĐỌC ĐƯỢC. Sổ chi phí dựng từ sổ cũ, rất nhiều dòng bỏ
   trống ô Cơ sở; chặn chúng là ngày bản này lên, nhân viên mở màn ra thấy gần như trắng và
   kết luận là mất dữ liệu. Chốt cơ sở lượt đầu quên nhánh này — `kiem-vai-bo-bo-phan.php` đỏ
   đúng chỗ, nên phép dưới giữ lại ngay tại đây cho khỏi mắc lần nữa. */
VHCP_Auth::dat_vai_tro( 'Admin', 'Sếp', '' );
VHCP_SoChi::add( array( 'ngay' => $today, 'coso' => '', 'loai' => 'Chi phí cơ sở',
	'noiDung' => 'Dòng cũ chưa khai cơ sở', 'soTien' => 111000, 'ky' => $KY ), '' );
t( '🔴 Bin đọc được dòng sổ chưa khai cơ sở',
	in_array( 'Dòng cũ chưa khai cơ sở', so_chi( 'Nhân viên', 'Bin' ), true ), so_chi( 'Nhân viên', 'Bin' ) );
t( '   Truyền cũng vậy',
	in_array( 'Dòng cũ chưa khai cơ sở', so_chi( 'Nhân viên', 'Truyền' ), true ), so_chi( 'Nhân viên', 'Truyền' ) );
t( '   nhưng dòng CÓ khai cơ sở thì vẫn bó như cũ',
	! in_array( 'Đá lạnh sổ phẳng', so_chi( 'Nhân viên', 'Bin' ), true ), so_chi( 'Nhân viên', 'Bin' ) );
/* Nhà khác thì không — chốt đơn vị đứng trước và không bị nhánh này nới. */
t( '🔴 NV POSH KHÔNG đọc được dòng vô chủ của nhà K&H',
	! in_array( 'Dòng cũ chưa khai cơ sở', so_chi( 'Nhân viên', 'NV POSH' ), true ), so_chi( 'Nhân viên', 'NV POSH' ) );

/* ═══ 7. XUẤT MISA ══════════════════════════════════════════════════════════════════ */
lam( 'Kế toán cá nhân', 'Chị Nhân' );
$ex   = VHCP_Misa::export_misa( 'all', 'chuaxuat', 'all' );
$tong = 0; $ma_co = array();
foreach ( $ex['rows'] as $rw ) { $tong += $rw[7]; $ma_co[ $rw[6] ] = 1; }
teq( '🔴 MISA lấy số THỰC CHI, không lấy dự toán', 1220000.0, (float) $tong );
teq( '🔴 TK Có = 141 theo hình thức chi (cột TK Có của người duyệt đã bỏ)', array( '141' => 1 ), $ma_co );
$co_bao = false;
foreach ( array_keys( $ex['warn'] ) as $w ) { if ( false !== mb_strpos( $w, 'người duyệt' ) ) { $co_bao = true; } }
t( '   và không còn cảnh báo "thiếu TK Có cho người duyệt"', ! $co_bao, $ex['warn'] );

/* ─────────────────────────────────────────────────────────────────────────────────── */
echo "\n";
if ( $TRUOT ) {
	echo '❌ TRƯỢT ' . count( $TRUOT ) . ' / ' . ( $DAT + count( $TRUOT ) ) . "\n";
	foreach ( $TRUOT as $x ) { echo "   • $x\n"; }
	exit( 1 );
}
echo "✅ ĐẠT $DAT / $DAT — trọn luồng chi phí cơ sở, phạm vi khớp nhau ở mọi màn\n";
