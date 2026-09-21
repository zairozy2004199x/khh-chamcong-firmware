<?php
/**
 * AI CHỈ THẤY PHẦN CỦA MÌNH — LOẠI CHI PHÍ TÍCH THEO VAI TRÒ.
 *
 * Anh Thắng 08/09/2026: *"thêm vai trò kế toán máy tự động (để chỉ thực hiện công việc bên bộ
 * phận máy tự động)"*, và trước đó gọi tắt mảng ấy là *"mảng mtd"*.
 *
 * =============================================================================================
 * 🔴 "MÁY TỰ ĐỘNG" KHÔNG PHẢI TÊN MỚI. Bên chấm công nó đã là một bộ phận thật từ lâu
 *    (`VHCC_Luong::BP_DS`: Máy tự động · Khu vui chơi · Văn phòng · Part time), và sổ nhân sự
 *    có sẵn người mang chức vụ ấy. Bên chi phí thì danh sách bộ phận chỉ có ở JAVASCRIPT, máy
 *    chủ không biết bộ phận nào có thật — nên ô "Bộ phận" trên tài khoản chỉ là chữ, không gác
 *    được gì.
 *
 * ⚠️ CHẠY THẬT. Khai vai, khai loại chi phí theo bộ phận, đổ dữ liệu, rồi ĐĂNG NHẬP làm từng
 *    người và đòi đúng những gì họ được thấy.
 *
 * Chạy: php tools/test/kiem-vai-bo-bo-phan.php
 */

require_once __DIR__ . '/wp-stub.php';
$goc = dirname( dirname( __DIR__ ) );
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );
vhcp_test_dat_gio( '2026-09-08 09:00:00' );

$dat = 0; $truot = array();
function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( null === $them ? '' : "\n      → " . ( is_scalar( $them ) ? $them : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) );
}
function teq( $ten, $mong, $thuc ) {
	t( $ten . ' (mong ' . json_encode( $mong, JSON_UNESCAPED_UNICODE ) . ')', $mong === $thuc, $thuc );
}
global $wpdb;

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 1. BỘ PHẬN "MÁY TỰ ĐỘNG" CÓ THẬT, VÀ HAI BÊN NÓI CÙNG MỘT DANH SÁCH
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
t( '🔴 máy chủ biết bộ phận "Máy tự động"',
	in_array( 'Máy tự động', VHCP_Cfg::BO_PHAN_DS, true ), VHCP_Cfg::BO_PHAN_DS );

/* 🔴 LUẬT NÀY ĐỔI NGÀY 10/09/2026 — ghi lại vì phép cũ trông vẫn hợp lý.
   Trước: hai danh sách GÕ CỨNG (hằng máy chủ + `BOPHAN_LIST` trong app.html) phải khớp từng
   tên, vì lệch một chữ là ô chọn bày ra một bộ phận mà máy chủ coi là không tồn tại.
   Nay: chỉ còn MỘT nguồn — bảng cấu hình, máy chủ gửi xuống `CFG.boPhanDs`. Không còn hai
   danh sách để mà lệch, nên phép "khớp từng tên" mất chỗ đứng.

   Thứ CÒN phải canh là hai chuyện khác:
     · giao diện KHÔNG được gõ cứng lại danh sách (gõ lại là dựng lại đúng cái bẫy vừa bỏ)
     · đường lui trên màn phải khớp hằng mặc định của máy chủ — hai bên đều dùng nó khi bảng
       chưa gieo, lệch nhau là lúc ấy hai bên nói hai danh sách khác nhau.
   Chốt đầy đủ cho bảng cấu hình nằm ở `kiem-bo-phan-khai-duoc.php`. */
$app = file_get_contents( dirname( dirname( __DIR__ ) ) . '/wordpress/vhcp-chi-phi/templates/app.html' );
t( '🔴 giao diện KHÔNG gõ cứng danh sách bộ phận nữa', false === strpos( $app, 'BOPHAN_LIST' ), '' );
$js = array();
if ( preg_match( "/var BOPHAN_MAC_DINH=\[([^\]]*)\]/u", $app, $m ) ) {
	foreach ( explode( ',', $m[1] ) as $x ) { $js[] = trim( trim( trim( $x ), "'" ) ); }
}
teq( 'đường lui trên màn khớp hằng mặc định của máy chủ từng tên',
	VHCP_Cfg::BO_PHAN_DS, $js );

/* Chuẩn hoá: nhận đúng tên, còn tên lạ thì trả '' (= không bó) chứ không nhận bừa. */
teq( 'nhận đúng tên bộ phận',        'Máy tự động', VHCP_Cfg::bo_phan_chuan( 'Máy tự động' ) );
teq( 'không phân biệt hoa thường',   'Máy tự động', VHCP_Cfg::bo_phan_chuan( 'MÁY TỰ ĐỘNG' ) );
teq( 'thừa khoảng trắng vẫn nhận',   'Máy tự động', VHCP_Cfg::bo_phan_chuan( '  Máy tự động ' ) );
/* 🔴 "MTD" là chữ anh Thắng gõ tắt trong tin nhắn, KHÔNG phải giá trị hợp lệ. Nhận bừa nó là
   nhận bừa mọi thứ gần giống, và lúc ấy không ai biết ô đó đang bó vào cái gì. */
teq( 'gõ tắt "MTD" KHÔNG được nhận bừa', '', VHCP_Cfg::bo_phan_chuan( 'MTD' ) );
teq( 'tên lạ -> rỗng (= mọi bộ phận)',    '', VHCP_Cfg::bo_phan_chuan( 'Bộ phận ma' ) );
teq( 'rỗng -> rỗng',                      '', VHCP_Cfg::bo_phan_chuan( '' ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 2. 🔴 VAI TRÒ KHÔNG CÒN MANG BỘ PHẬN — CHỈ CÒN MỘT TRỤC
 * ═══════════════════════════════════════════════════════════════════════════════════════════
 * Anh Thắng 21/09/2026: *"đang có sự xung đột giữa vai trò và bộ phận, dẫn đến set cái này thì
 * mất cái kia"*, rồi chốt *"bỏ vai trò đi, cho bộ phận dùng chung"*.
 *
 * ⚠️ MỤC NÀY TRƯỚC ĐÂY ĐÒI ĐIỀU NGƯỢC LẠI — "VAI THẮNG Ô TRÊN TÀI KHOẢN" — và nó XANH suốt.
 *    Nhưng `bo_phan_cua_nguoi()` mà nó gọi CHƯA TỪNG được mã chạy gọi tới: `dat_vai_tro()` xưa
 *    nay vẫn đọc bộ phận từ HÀNG NGƯỜI DÙNG. Tức bài kiểm canh một luật mà sản phẩm không hề
 *    thi hành — xanh đều, mà cái nó bảo vệ thì không tồn tại. Đó cũng chính là gốc của cái
 *    "xung đột" anh Thắng thấy: màn Cấu hình cho khai ở hai nơi, còn máy chủ chỉ nghe một nơi.
 *
 * 🔴 NÊN TỪ BẢN NÀY PHÉP ĐI THEO HƯỚNG KHÁC: đòi cột bộ phận của vai BIẾN MẤT, và đòi bộ phận
 *    thật sự có hiệu lực đúng bằng ô trên HÀNG NGƯỜI DÙNG.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
t( '🔴 `bo_phan_cua_nguoi()` đã bỏ hẳn (trục thứ hai không còn cửa nào)',
	! method_exists( 'VHCP_Cfg', 'bo_phan_cua_nguoi' ) );

VHCP_Cfg::write( VHCP_Cfg::VAI, array(
	/* Dòng cũ CÒN ô thứ ba trong sổ — phải bị làm ngơ, không phải đọc rồi dùng. */
	array( 'Kế toán máy tự động', 'Kế toán cá nhân', 'Máy tự động' ),
	array( 'Kế toán chung',       'Kế toán cá nhân', '' ),
) );
$vt = VHCP_Cfg::vai_tuy_bien();
$theo = array();
foreach ( $vt as $v ) { $theo[ $v['ten'] ] = $v; }
teq( 'vẫn đọc ra đủ hai vai', 2, count( $vt ) );
t( '🔴 vai KHÔNG còn mang khoá `boPhan`', ! array_key_exists( 'boPhan', $theo['Kế toán máy tự động'] ),
	$theo['Kế toán máy tự động'] );
teq( '   và vẫn kế thừa đúng vai gốc', 'Kế toán cá nhân', VHCP_Cfg::vai_goc( 'Kế toán máy tự động' ) );

/* 🔴 CỬA LƯU PHẢI DỌN Ô CŨ, KHÔNG PHẢI GIỮ IM. Để nguyên ô thứ ba là dữ liệu chết nằm lại
   trong sổ, và lượt nào đó về sau có người viết lại mã đọc nó thì trục cũ sống dậy. */
VHCP_Auth::dat_vai_tro( 'Admin', 'Sếp' );
VHCP_Cfg::save_config( array( 'vaiTro' => array(
	array( 'ten' => 'Kế toán máy tự động', 'goc' => 'Kế toán cá nhân', 'boPhan' => 'Máy tự động' ),
) ) );
$hang = VHCP_Cfg::read( VHCP_Cfg::VAI );
teq( 'lưu xong còn đúng một vai', 1, count( $hang ) );
teq( '🔴 và dòng chỉ còn HAI ô — ô bộ phận đã dọn', array( 'Kế toán máy tự động', 'Kế toán cá nhân' ),
	array_values( array_slice( array_values( (array) $hang[0] ), 0, 2 ) ) );
t( '   không còn ô thứ ba mang tên bộ phận',
	'' === trim( (string) ( isset( $hang[0][2] ) ? $hang[0][2] : '' ) ), $hang[0] );

/* 🔴 BỘ PHẬN CÓ HIỆU LỰC = Ô TRÊN HÀNG NGƯỜI DÙNG, và chỉ nó. Đây là điều sản phẩm VẪN LÀM từ
   trước; nay nó là điều DUY NHẤT, nên phải có phép canh thật thay vì suy ra. */
VHCP_Auth::dat_vai_tro( 'Kế toán máy tự động', 'Chị Kế Toán MTĐ', '', 'Máy tự động' );
teq( '🔴 bộ phận bó lấy từ ô trên tài khoản', 'Máy tự động', VHCP_Auth::bo_phan_bo() );
/* Cùng một VAI ấy, nhưng tài khoản bỏ trống ô bộ phận -> không bó. Trước đây vai sẽ "thắng"
   và vẫn bó — đúng cái hành vi vừa bỏ. */
VHCP_Auth::dat_vai_tro( 'Kế toán máy tự động', 'Anh Kế Toán Chung', '', '' );
teq( '🔴 cùng vai ấy mà ô tài khoản trống thì KHÔNG bó', '', VHCP_Auth::bo_phan_bo() );
VHCP_Auth::dat_vai_tro( 'Admin', 'Sếp', '', 'Máy tự động' );
teq( 'Admin không bao giờ bị bó, dù ô có khai', '', VHCP_Auth::bo_phan_bo() );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 3. LOẠI CHI PHÍ THUỘC BỘ PHẬN NÀO — và ai được đọc dòng mang loại ấy
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
/* ⚠️ TỪ 21/09/2026 Ô TÍCH LÀ VAI TRÒ, KHÔNG CÒN LÀ BỘ PHẬN — anh Thắng: *"bỏ tích bộ phận đi,
   mà tích theo vai trò"*. Cả bài này vẫn kiểm đúng một chuyện: *"ai chỉ thấy phần của mình"*.
   Chỉ TRỤC đổi, nên chỗ gieo đổi theo: cột 11 khai TÊN VAI được dùng. Cột 5 (Bộ phận) giữ
   nguyên trong sổ để chứng minh nó KHÔNG còn cắt gì nữa. */
VHCP_Cfg::write( VHCP_Cfg::LOAI, array(
	/* cột: loại · TKnợ · TKcó · mãđt · bộphận · ghichú · tênMISA · loại · đơnvị · khối · VAI TRÒ */
	array( 'Sửa máy gắp thú', '6427', '', '', 'Máy tự động', '', '', '', '', '', 'Kế toán máy tự động' ),
	array( 'Chạy quảng cáo',  '6417', '', '', 'Marketing',   '', '', '', '', '', 'Kế toán marketing' ),
	array( 'Chi phí khác',    '6428', '', '', '',            '', '', '', '', '', '' ),   // CHƯA tích vai nào
) );
teq( 'loại "Sửa máy gắp thú" tích cho vai kế toán máy tự động',
	'Kế toán máy tự động', VHCP_Cfg::loai_tk( 'Sửa máy gắp thú' )['vaiTro'] );
teq( 'loại "Chạy quảng cáo" tích cho vai khác',
	'Kế toán marketing', VHCP_Cfg::loai_tk( 'Chạy quảng cáo' )['vaiTro'] );
teq( 'loại chưa tích vai nào -> rỗng (= mọi vai)', '', VHCP_Cfg::loai_tk( 'Chi phí khác' )['vaiTro'] );
/* Cột Bộ phận cũ vẫn còn nguyên trong sổ — thôi dùng chứ không xoá. */
teq( 'ô Bộ phận cũ vẫn còn trong sổ', 'Máy tự động', VHCP_Cfg::bo_phan_cua_loai( 'Sửa máy gắp thú' ) );
teq( 'loại không có trong danh mục -> rỗng',     '',            VHCP_Cfg::bo_phan_cua_loai( 'Loại lạ hoắc' ) );

/* Đăng nhập bằng đúng tên người LẬP ĐƠN thử. Vai Nhân viên vốn chỉ thấy đơn của chính mình
   (luật có sẵn, không liên quan bản này) — đăng nhập tên khác thì bảng rỗng vì lý do đó, và
   phép thử sẽ đổ lỗi nhầm cho chốt bộ phận. */
/* 🔴 PHÒNG BAN ĐI THEO TÀI KHOẢN, KHÔNG THEO VAI (đổi 13/09/2026). Anh Thắng: *"anh sẽ tạo
   ban bệ phòng ban sẵn, ai thuộc bộ phận nào thì thêm vào, tránh sai vai hay tự tạo vai lạ"*.
   Trước bản ấy bộ phận khai ở cột "Chỉ làm bộ phận" của bảng VAI TRÒ, nên `lam()` chỉ cần
   truyền tên vai. Nay nó là tham số thứ tư của `dat_vai_tro()` — đúng như cổng API truyền
   xuống từ ô Bộ phận của tài khoản. */
function lam( $vai, $ten = 'NV', $bp = '' ) { VHCP_Auth::dat_vai_tro( $vai, $ten, '', $bp ); }

lam( 'Kế toán máy tự động', 'NV', 'Máy tự động' );
teq( '🔴 đọc được dòng đã tích cho vai mình',  true,  VHCP_Auth::xem_duoc_loai( 'Sửa máy gắp thú' ) );
teq( '🔴 KHÔNG đọc được dòng tích cho vai khác', false, VHCP_Auth::xem_duoc_loai( 'Chạy quảng cáo' ) );
/* 🔴 Danh mục của anh Thắng dựng từ sổ cũ, rất nhiều dòng còn bỏ trống ô này. Chặn chúng lại
   là ngày bản này lên, kế toán mở màn ra thấy gần như trắng. */
teq( '🔴 loại CHƯA tích vai nào thì vẫn đọc được', true, VHCP_Auth::xem_duoc_loai( 'Chi phí khác' ) );
teq( 'loại không có trong danh mục cũng đọc được', true, VHCP_Auth::xem_duoc_loai( 'Loại lạ hoắc' ) );

/* 🔴 VAI GỐC KHÔNG PHẢI CHÌA KHOÁ VẠN NĂNG. Trước 21/09/2026, vai nào không bị bó bộ phận thì
   đọc được tất; nay ô tích ghi TÊN VAI, nên "Kế toán cá nhân" không tự động thừa hưởng những
   dòng tích cho vai con của nó. Đổi được điều này thì cả nhánh nhìn thấy sổ của nhau. */
lam( 'Kế toán cá nhân' );
teq( '🔴 vai gốc KHÔNG đọc được dòng tích riêng cho vai con', false, VHCP_Auth::xem_duoc_loai( 'Sửa máy gắp thú' ) );
teq( '   kể cả dòng của nhánh khác',                          false, VHCP_Auth::xem_duoc_loai( 'Chạy quảng cáo' ) );
teq( '   nhưng dòng CHƯA tích ai thì vẫn đọc được',           true,  VHCP_Auth::xem_duoc_loai( 'Chi phí khác' ) );
/* Admin thì qua hết, luôn luôn. */
lam( 'Admin' );
teq( '🔴 Admin đọc được mọi dòng, kể cả dòng tích riêng', true, VHCP_Auth::xem_duoc_loai( 'Sửa máy gắp thú' ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 4. SỔ CHI PHÍ — chạy thật qua `list_chi()`
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$thieu = vhcp_test_bang_thieu( 'so_chi' );
t( 'bảng so_chi dựng được (không thì mọi phép dưới xanh oan)', ! $thieu, $thieu );

$wpdb->insert( VHCP_DB::t( 'so_chi' ), array( 'id' => 'S_MTD', 'ky' => 'T9', 'loai' => 'Sửa máy gắp thú', 'so_tien' => 100000 ) );
$wpdb->insert( VHCP_DB::t( 'so_chi' ), array( 'id' => 'S_MKT', 'ky' => 'T9', 'loai' => 'Chạy quảng cáo',  'so_tien' => 200000 ) );
$wpdb->insert( VHCP_DB::t( 'so_chi' ), array( 'id' => 'S_KHAC','ky' => 'T9', 'loai' => 'Chi phí khác',    'so_tien' => 300000 ) );

function sc_ids() {
	$r = VHCP_SoChi::list_chi();
	$a = array();
	foreach ( $r['items'] as $x ) { $a[] = (string) $x['id']; }
	sort( $a );
	return $a;
}
/* Đối chứng chạy thật qua `list_chi()`: Admin thấy cả ba; vai gốc chỉ thấy dòng chưa tích ai. */
lam( 'Admin' );
teq( 'đối chứng · Admin thấy cả ba dòng', array( 'S_KHAC', 'S_MKT', 'S_MTD' ), sc_ids() );
lam( 'Kế toán cá nhân' );
teq( '🔴 đối chứng · vai gốc chỉ thấy dòng CHƯA tích ai', array( 'S_KHAC' ), sc_ids() );
lam( 'Kế toán máy tự động', 'NV', 'Máy tự động' );
teq( '🔴 kế toán máy tự động chỉ thấy dòng của mình + dòng chưa phân loại',
	array( 'S_KHAC', 'S_MTD' ), sc_ids() );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 5. ĐƠN CHI PHÍ — "CHẠM LÀ THẤY"
 *
 * 🔴 Một đơn có nhiều dòng chi, mỗi dòng một loại, nên đơn KHÔNG thuộc đúng một bộ phận. Đòi
 *    MỌI dòng đều thuộc bộ phận mình thì một đơn lẫn hai loại sẽ biến mất khỏi cả hai màn, và
 *    tiền treo mà không kế toán nào thấy để xử.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
function them_don( $ma, $dong ) {
	global $wpdb;
	$wpdb->insert( VHCP_DB::t( 'don' ), array( 'ma_don' => $ma, 'ky' => 'T9', 'trang_thai' => 'Chờ quyết toán', 'nguoi_lap' => 'NV' ) );
	foreach ( $dong as $i => $loai ) {
		$wpdb->insert( VHCP_DB::t( 'chiphi' ), array(
			'id' => $ma . '_' . $i, 'ma_don' => $ma, 'coso' => 'CS', 'nhom' => $loai, 'thanh_tien' => 1000 ) );
	}
}
them_don( 'D_MTD',  array( 'Sửa máy gắp thú' ) );
them_don( 'D_MKT',  array( 'Chạy quảng cáo' ) );
them_don( 'D_LAN',  array( 'Chạy quảng cáo', 'Sửa máy gắp thú' ) );   // lẫn hai bộ phận
them_don( 'D_TRONG', array() );                                        // đơn xin ứng trước, chưa có dòng nào

function don_mas() {
	$a = array();
	foreach ( VHCP_Don::list_dons() as $x ) { $a[] = (string) $x['maDon']; }
	sort( $a );
	return $a;
}
lam( 'Kế toán cá nhân' );
teq( 'đối chứng · kế toán thường thấy cả bốn đơn',
	array( 'D_LAN', 'D_MKT', 'D_MTD', 'D_TRONG' ), don_mas() );
lam( 'Kế toán máy tự động', 'NV', 'Máy tự động' );
teq( '🔴 kế toán máy tự động: thấy đơn của mình, đơn LẪN, và đơn chưa có dòng nào — không thấy đơn thuần marketing',
	array( 'D_LAN', 'D_MTD', 'D_TRONG' ), don_mas() );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 5a. KHỐI "ĐƠN ĐANG LÊN CHỜ KẾ TOÁN" TRÊN MÀN TỔNG QUAN
 *
 * Anh Thắng 08/09/2026, sau khi gán vai cho một tài khoản: *"chức năng như kế toán cá nhân,
 * nhưng chỉ xem bên bộ phận của mình"* — kèm ảnh màn Tổng quan vẫn liệt kê đủ mọi mảng.
 * Khối ấy đi qua `pending_modules()`, một đường riêng mà bản 1.82.0 chưa gác.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$wpdb->insert( VHCP_DB::t( 'da_index' ), array( 'ma_da' => 'DA1', 'ten' => 'setup gian', 'trang_thai' => 'Chờ kế toán duyệt', 'nguoi_tao' => 'NV' ) );
$wpdb->insert( VHCP_DB::t( 'mk_don' ),   array( 'ma' => 'MK1', 'coso' => 'CS', 'ten' => 'ads', 'nguoi_tao' => 'NV' ) );
$wpdb->insert( VHCP_DB::t( 'bp_index' ), array( 'ma' => 'BP1', 'loai' => 'Công tác', 'ten' => 'đi tỉnh', 'nguoi_tao' => 'NV' ) );

function mang_cho() {
	$r = VHCP_Report::pending_modules();
	$a = array();
	foreach ( (array) $r['items'] as $x ) { $a[] = (string) $x['module']; }
	sort( $a );
	return $a;
}
lam( 'Kế toán cá nhân' );
teq( 'đối chứng · kế toán thường thấy cả ba mảng',
	array( 'Công tác', 'Kỹ thuật', 'Marketing' ), mang_cho() );
lam( 'Kế toán máy tự động', 'NV', 'Máy tự động' );
teq( '🔴 kế toán máy tự động KHÔNG thấy mảng nào trong bốn mảng kia',
	array(), mang_cho() );

/* Và một vai bó vào ĐÚNG một trong mấy mảng ấy thì chỉ thấy mảng của mình — không thì luật
   trên chỉ đúng nhờ may: "Máy tự động" tình cờ không trùng tên mảng nào. */
VHCP_Cfg::write( VHCP_Cfg::VAI, array(
	array( 'Kế toán máy tự động', 'Kế toán cá nhân', 'Máy tự động' ),
	array( 'Kế toán chung',       'Kế toán cá nhân', '' ),
	array( 'Kế toán marketing',   'Kế toán cá nhân', 'Marketing' ),
) );
lam( 'Kế toán marketing', 'NV', 'Marketing' );
teq( '🔴 vai bó Marketing chỉ thấy mảng Marketing', array( 'Marketing' ), mang_cho() );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 5b. 🔴 PHẦN ĐANG CHẠY KHÔNG ĐƯỢC ĐỘNG VÀO
 *
 * Anh Thắng 08/09/2026: *"nhớ đừng can thiệp gì bên phần chi phí khu vui chơi. mình đang làm
 * mảng mới posh"*.
 *
 * Bản này thêm một luật bó theo bộ phận. Luật mới mà đụng vào người đang chạy thì sáng hôm sau
 * cả khu vui chơi mở màn ra thấy thiếu đơn — và họ không có cách nào đoán ra vì sao. Chốt: ai
 * KHÔNG mang vai bó bộ phận thì thấy y nguyên như trước, bất kể vai gì.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
/* ⚠️ TỪ 21/09/2026 luật là TÍCH THEO VAI. Nên câu "người đang chạy không bị đụng" nay đọc là:
   SITE CHƯA TÍCH GÌ THÌ MỌI VAI THẤY Y NGUYÊN NHƯ TRƯỚC. Đó mới là điều đáng canh — ngày bản
   này lên, chưa ai kịp tích ô nào, mà màn hình đã đổi thì cả công ty đứng hình.
   Nên gỡ hết ô tích rồi mới đo. (Ca "đã tích rồi" nằm ở mục 3 và ở `kiem-loai-theo-vai.php`.) */
VHCP_Cfg::write( VHCP_Cfg::LOAI, array(
	array( 'Sửa máy gắp thú', '6427', '', '', 'Máy tự động', '', '', '', '', '', '' ),
	array( 'Chạy quảng cáo',  '6417', '', '', 'Marketing',   '', '', '', '', '', '' ),
	array( 'Chi phí khác',    '6428', '', '', '',            '', '', '', '', '', '' ),
), false );
VHCP_Cfg::clear_cache();
$MOI = array( 'S_KHAC', 'S_MKT', 'S_MTD' );
$DON = array( 'D_LAN', 'D_MKT', 'D_MTD', 'D_TRONG' );
foreach ( array( 'Admin', 'Quản lý', 'Kế toán cá nhân', 'Kế toán NCC', 'Nhân viên', 'Kế toán chung' ) as $v ) {
	lam( $v );
	teq( 'và thấy đủ sổ chi phí như trước · ' . $v, $MOI, sc_ids() );
	teq( 'và thấy đủ đơn như trước · ' . $v,        $DON, don_mas() );
}
/* 🔴 Ô "BỘ PHẬN / LOẠI NV" TRÊN TÀI KHOẢN KHÔNG ĐƯỢC CẮT DỮ LIỆU.
   Ô ấy xưa nay chỉ lọc DANH MỤC lúc nhập và phân quyền TAB cho Nhân viên. Ảnh anh Thắng gửi
   08/09/2026 đã có sẵn một dòng khai "Bộ phận: Kỹ thuật"; biến ô đó thành lát cắt là tài khoản
   ấy mất đơn ngay lúc cài đè. Bản nháp đầu của chính bản này đã sai đúng như thế, và phép dưới
   là thứ bắt được. */
/* ⚠️ Phép cũ ở đây gọi `VHCP_Cfg::bo_phan_cua_nguoi( 'Kế toán cá nhân' )` — hàm đã bỏ
   21/09/2026 cùng cả trục bộ phận-theo-vai. Điều nó muốn nói vẫn đúng và vẫn phải canh, chỉ
   là nói bằng hàm còn sống: một VAI GỐC không tự bó gì; bó hay không là do ô trên tài khoản. */
VHCP_Auth::dat_vai_tro( 'Kế toán cá nhân', 'Chị Kế Toán', '', '' );
teq( '🔴 vai gốc + ô Bộ phận trống thì KHÔNG bó gì cả', '', VHCP_Auth::bo_phan_bo() );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 5c. MÀN PHẢI NÓI RA LÀ MÌNH ĐANG BỊ BÓ
 *
 * 🔴 Không có dòng ấy thì màn trông y hệt lúc không bó, và người dùng kết luận là tính năng
 *    không chạy — anh Thắng đã kết luận đúng như thế khi nhìn màn Tổng quan còn nguyên 21 mục.
 *    Số loại CHƯA khai bộ phận là thứ biến "trông như hỏng" thành "còn N dòng phải khai".
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
lam( 'Kế toán máy tự động', 'NV', 'Máy tự động' );
$b = VHCP_Don::get_bootstrap();
teq( '🔴 boot nói rõ đang bó bộ phận nào', 'Máy tự động', $b['boPhanBo'] );
/* Dữ liệu thử có ba loại trên các dòng chi: "Sửa máy gắp thú" (Máy tự động), "Chạy quảng cáo"
   (Marketing) và... chỉ hai loại ấy nằm trên bảng `chiphi`. Loại "Chi phí khác" chỉ có ở sổ
   chi phí, không có dòng chi nào — nên không được đếm. */
teq( 'và đếm đúng số LOẠI trên dòng chi chưa khai bộ phận', 0, $b['loaiChuaBP'] );

$wpdb->insert( VHCP_DB::t( 'chiphi' ), array(
	'id' => 'CP_MOI', 'ma_don' => 'D_MTD', 'coso' => 'CS', 'nhom' => 'Loại chưa khai', 'thanh_tien' => 1 ) );
$b2 = VHCP_Don::get_bootstrap();
teq( '🔴 thêm một dòng mang loại chưa khai -> đếm lên 1', 1, $b2['loaiChuaBP'] );
$wpdb->insert( VHCP_DB::t( 'chiphi' ), array(
	'id' => 'CP_MOI2', 'ma_don' => 'D_MTD', 'coso' => 'CS', 'nhom' => 'Loại chưa khai', 'thanh_tien' => 1 ) );
$b3 = VHCP_Don::get_bootstrap();
teq( 'thêm dòng thứ hai CÙNG loại thì vẫn là 1 (đếm loại, không đếm dòng)', 1, $b3['loaiChuaBP'] );

lam( 'Kế toán cá nhân' );
$b4 = VHCP_Don::get_bootstrap();
teq( '🔴 người KHÔNG bó thì boot trả rỗng -> màn không hiện dải nhắc', '', $b4['boPhanBo'] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 6. KHÔNG CÒN VAI NÀO ĐƯỢC DỰNG SẴN — anh Thắng 14/09/2026: *"bỏ cái này, vì phân quyền trang
 *    nên không cần nữa"*
 *
 * ⚠️ PHÉP NÀY ĐÃ ĐẢO CHIỀU. Bản trước canh ngược lại: vai "Kế toán máy tự động" PHẢI được dựng
 *    sẵn, vì hồi ấy ba mảng dùng chung MỘT trang chi phí và chỉ có cột Bộ phận của vai ấy mới
 *    tách được sổ. Nay mỗi mảng một trang riêng (/chi-phi-kvc · /chi-phi-mtd · /chi-phi-vp), mỗi
 *    trang một bộ bảng riêng — vào đúng trang là đã chỉ thấy mảng ấy. Vai dựng sẵn thành thừa,
 *    và thừa ở đây không vô hại: nó hiện lại trong bảng Vai trò tự tạo của CẢ BỐN trang.
 *
 * 🔴 Ghi rõ để lần sau khỏi tưởng bài kiểm hỏng mà "vá" ngược lại: mất phép này thì mỗi lượt cài
 *    mới lại đẻ ra một vai anh vừa cố ý bỏ đi.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$cfg_ma_vai = file_get_contents( dirname( dirname( __DIR__ ) ) . '/wordpress/vhcp-chi-phi/includes/class-vhcp-cfg.php' );

VHCP_Cfg::write( VHCP_Cfg::VAI, array() );
VHCP_Meta::set( 'seeded_vai_mtd_v1', '' );   // xoá cả cờ: giả cảnh cài mới tinh
VHCP_Cfg::clear_cache();
VHCP_Cfg::cfg_static();   // lượt đọc này chạy seed

teq( '🔴 cài mới: seed KHÔNG tự đẻ vai nào', 0, count( VHCP_Cfg::vai_tuy_bien() ) );
t( '🔴 và mã nguồn không còn nhánh dựng vai "Kế toán máy tự động"',
	false !== strpos( $cfg_ma_vai, 'ĐÃ BỎ' )
		&& false === strpos( $cfg_ma_vai, "self::append( self::VAI, array( 'Kế toán máy tự động'" ),
	'' );

/* Bỏ dựng sẵn KHÔNG được làm hỏng cơ chế vai tự tạo — anh vẫn khai tay được.
   ⚠️ Từ 21/09/2026 vai KHÔNG còn mang bộ phận, nên chỗ này chỉ còn đòi tên + vai gốc. Bó bộ
      phận là việc của ô trên hàng người dùng (mục 2). */
VHCP_Cfg::write( VHCP_Cfg::VAI, array(
	array( 'Kế toán máy tự động', 'Kế toán cá nhân' ),
) );
VHCP_Cfg::clear_cache();
$ten_vai = array();
foreach ( VHCP_Cfg::vai_tuy_bien() as $v ) { $ten_vai[ $v['ten'] ] = $v; }
t( 'khai tay thì vai ấy vẫn nhận', isset( $ten_vai['Kế toán máy tự động'] ), array_keys( $ten_vai ) );
if ( isset( $ten_vai['Kế toán máy tự động'] ) ) {
	teq( 'và vẫn kế thừa Kế toán cá nhân', 'Kế toán cá nhân', VHCP_Cfg::vai_goc( 'Kế toán máy tự động' ) );
}

/* Và xoá đi thì lượt sau vẫn ở yên đã xoá. */
VHCP_Cfg::write( VHCP_Cfg::VAI, array() );
VHCP_Cfg::clear_cache();
VHCP_Cfg::cfg_static();
teq( '🔴 xoá vai đi thì lượt sau KHÔNG dựng lại', 0, count( VHCP_Cfg::vai_tuy_bien() ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════ */
if ( $truot ) {
	echo "\n✗ TRƯỢT " . count( $truot ) . " phép (đạt $dat):\n";
	foreach ( $truot as $x ) { echo "  · $x\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $dat phép: loại chi phí lọc theo vai, chưa tích thì mọi vai thấy, Admin thấy hết.\n";
