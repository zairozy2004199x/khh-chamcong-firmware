<?php
/**
 * VAI "KẾ TOÁN MÁY TỰ ĐỘNG" — CHỈ LÀM VIỆC TRONG BỘ PHẬN CỦA MÌNH.
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
 * 2. VAI MANG BỘ PHẬN — VÀ VAI THẮNG Ô TRÊN TÀI KHOẢN
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
VHCP_Cfg::write( VHCP_Cfg::VAI, array(
	/* cột: tên vai · kế thừa · BỘ PHẬN bó */
	array( 'Kế toán máy tự động', 'Kế toán cá nhân', 'Máy tự động' ),
	array( 'Kế toán chung',       'Kế toán cá nhân', '' ),
	array( 'Vai khai bừa',        'Kế toán cá nhân', 'Bộ phận ma' ),
) );
$vt = VHCP_Cfg::vai_tuy_bien();
$bp_theo_ten = array();
foreach ( $vt as $v ) { $bp_theo_ten[ $v['ten'] ] = $v['boPhan']; }
teq( 'vai "Kế toán máy tự động" mang bộ phận Máy tự động', 'Máy tự động', $bp_theo_ten['Kế toán máy tự động'] );
teq( 'vai không khai bộ phận thì để trống', '', $bp_theo_ten['Kế toán chung'] );
teq( '🔴 vai khai tên bộ phận LẠ thì coi như không bó', '', $bp_theo_ten['Vai khai bừa'] );
teq( 'và vẫn kế thừa đúng vai gốc', 'Kế toán cá nhân', VHCP_Cfg::vai_goc( 'Kế toán máy tự động' ) );

/* 🔴 LƯU QUA MÀN CẤU HÌNH RỒI ĐỌC LẠI PHẢI CÒN NGUYÊN. Ghi thẳng bằng `write()` như trên chỉ
   chứng minh chốt ĐỌC chạy đúng; nếu đường GHI đánh rơi cột thứ ba thì lần đầu anh Thắng bấm
   Lưu ở bảng Vai trò là mọi vai mất bó, và người mang vai ấy nhìn thấy sổ của mọi mảng.

   ⚠️ PHẢI ĐĂNG NHẬP ADMIN, VÀ PHẢI DỌN BẢNG TRƯỚC. `save_config()` chối người không phải Admin
      ("Chỉ Admin mới thêm/sửa vai trò được") — không đăng nhập thì lượt lưu KHÔNG chạy, bảng
      vẫn giữ nguyên mấy dòng `write()` ở trên, và phép này xanh mà chẳng kiểm được gì. Đã xanh
      oan đúng như thế ở bản nháp đầu; phá thử chỉ ra. */
VHCP_Auth::dat_vai_tro( 'Admin', 'Sếp' );
VHCP_Cfg::write( VHCP_Cfg::VAI, array() );
teq( 'dọn sạch bảng vai trước đã', 0, count( VHCP_Cfg::vai_tuy_bien() ) );
VHCP_Cfg::save_config( array( 'vaiTro' => array(
	array( 'ten' => 'Kế toán máy tự động', 'goc' => 'Kế toán cá nhân', 'boPhan' => 'Máy tự động' ),
	array( 'ten' => 'Kế toán chung',       'goc' => 'Kế toán cá nhân', 'boPhan' => '' ),
) ) );
teq( '🔴 lưu qua màn Cấu hình xong vai vẫn còn bó',
	'Máy tự động', VHCP_Cfg::bo_phan_cua_nguoi( 'Kế toán máy tự động' ) );
teq( 'và vai không bó thì vẫn không bó', '', VHCP_Cfg::bo_phan_cua_nguoi( 'Kế toán chung' ) );
/* Khai bừa qua đường lưu cũng phải bị rửa, y như đường đọc. */
VHCP_Cfg::save_config( array( 'vaiTro' => array(
	array( 'ten' => 'Kế toán máy tự động', 'goc' => 'Kế toán cá nhân', 'boPhan' => 'Máy tự động' ),
	array( 'ten' => 'Kế toán chung',       'goc' => 'Kế toán cá nhân', 'boPhan' => '' ),
	array( 'ten' => 'Vai khai bừa',        'goc' => 'Kế toán cá nhân', 'boPhan' => 'MTD' ),
) ) );
teq( 'lưu tên bộ phận lạ thì rửa thành không bó', '', VHCP_Cfg::bo_phan_cua_nguoi( 'Vai khai bừa' ) );

/* 🔴 VAI THẮNG Ô TRÊN TÀI KHOẢN. Nếu tài khoản thắng thì chỉ cần xoá một ô là vai "Kế toán máy
   tự động" nhìn thấy cả sổ của mọi mảng — mà xoá một ô thì không ai coi là việc nguy hiểm. */
teq( 'vai có khai bộ phận thì bó', 'Máy tự động', VHCP_Cfg::bo_phan_cua_nguoi( 'Kế toán máy tự động' ) );
teq( 'vai không khai thì không bó',  '',            VHCP_Cfg::bo_phan_cua_nguoi( 'Kế toán chung' ) );
teq( 'vai gốc không bao giờ bó',     '',            VHCP_Cfg::bo_phan_cua_nguoi( 'Kế toán cá nhân' ) );
teq( 'Admin không bao giờ bó',       '',            VHCP_Cfg::bo_phan_cua_nguoi( 'Admin' ) );
teq( 'vai lạ (đã xoá khỏi bảng) -> không bó', '',   VHCP_Cfg::bo_phan_cua_nguoi( 'Vai đã xoá' ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 3. LOẠI CHI PHÍ THUỘC BỘ PHẬN NÀO — và ai được đọc dòng mang loại ấy
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
VHCP_Cfg::write( VHCP_Cfg::LOAI, array(
	/* cột: loại · TK nợ · TK có · mã đt · BỘ PHẬN · ghi chú · tên MISA · loại */
	array( 'Sửa máy gắp thú', '6427', '', '', 'Máy tự động', '', '', '' ),
	array( 'Chạy quảng cáo',  '6417', '', '', 'Marketing',   '', '', '' ),
	array( 'Chi phí khác',    '6428', '', '', '',            '', '', '' ),   // CHƯA khai bộ phận
) );
teq( 'loại "Sửa máy gắp thú" thuộc Máy tự động', 'Máy tự động', VHCP_Cfg::bo_phan_cua_loai( 'Sửa máy gắp thú' ) );
teq( 'loại "Chạy quảng cáo" thuộc Marketing',    'Marketing',   VHCP_Cfg::bo_phan_cua_loai( 'Chạy quảng cáo' ) );
teq( 'loại chưa khai bộ phận -> rỗng',           '',            VHCP_Cfg::bo_phan_cua_loai( 'Chi phí khác' ) );
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
teq( 'người bó bộ phận đọc đúng bộ phận của mình', 'Máy tự động', VHCP_Auth::bo_phan_bo() );
teq( '🔴 đọc được dòng của bộ phận mình',  true,  VHCP_Auth::xem_duoc_loai( 'Sửa máy gắp thú' ) );
teq( '🔴 KHÔNG đọc được dòng của mảng khác', false, VHCP_Auth::xem_duoc_loai( 'Chạy quảng cáo' ) );
/* 🔴 Danh mục của anh Thắng dựng từ sổ cũ, rất nhiều dòng còn bỏ trống ô Bộ phận. Chặn chúng
   lại là ngày bản này lên, kế toán bó bộ phận mở màn ra thấy gần như trắng. */
teq( '🔴 loại CHƯA khai bộ phận thì vẫn đọc được', true, VHCP_Auth::xem_duoc_loai( 'Chi phí khác' ) );
teq( 'loại không có trong danh mục cũng đọc được', true, VHCP_Auth::xem_duoc_loai( 'Loại lạ hoắc' ) );

lam( 'Kế toán cá nhân' );
teq( 'kế toán thường không bị bó', '', VHCP_Auth::bo_phan_bo() );
teq( 'nên đọc được mọi mảng · máy tự động', true, VHCP_Auth::xem_duoc_loai( 'Sửa máy gắp thú' ) );
teq( 'nên đọc được mọi mảng · marketing',   true, VHCP_Auth::xem_duoc_loai( 'Chạy quảng cáo' ) );

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
lam( 'Kế toán cá nhân' );
teq( 'đối chứng · kế toán thường thấy cả ba dòng', array( 'S_KHAC', 'S_MKT', 'S_MTD' ), sc_ids() );
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
$MOI = array( 'S_KHAC', 'S_MKT', 'S_MTD' );
$DON = array( 'D_LAN', 'D_MKT', 'D_MTD', 'D_TRONG' );
foreach ( array( 'Admin', 'Quản lý', 'Kế toán cá nhân', 'Kế toán NCC', 'Nhân viên', 'Kế toán chung' ) as $v ) {
	lam( $v );
	teq( '🔴 vai "' . $v . '" KHÔNG bị bó bộ phận', '', VHCP_Auth::bo_phan_bo() );
	teq( 'và thấy đủ sổ chi phí như trước · ' . $v, $MOI, sc_ids() );
	teq( 'và thấy đủ đơn như trước · ' . $v,        $DON, don_mas() );
}
/* 🔴 Ô "BỘ PHẬN / LOẠI NV" TRÊN TÀI KHOẢN KHÔNG ĐƯỢC CẮT DỮ LIỆU.
   Ô ấy xưa nay chỉ lọc DANH MỤC lúc nhập và phân quyền TAB cho Nhân viên. Ảnh anh Thắng gửi
   08/09/2026 đã có sẵn một dòng khai "Bộ phận: Kỹ thuật"; biến ô đó thành lát cắt là tài khoản
   ấy mất đơn ngay lúc cài đè. Bản nháp đầu của chính bản này đã sai đúng như thế, và phép dưới
   là thứ bắt được. */
teq( '🔴 ô Bộ phận trên tài khoản KHÔNG bó gì cả',
	'', VHCP_Cfg::bo_phan_cua_nguoi( 'Kế toán cá nhân' ) );

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
 * 6. VAI ĐƯỢC DỰNG SẴN — anh Thắng không phải khai tay
 *
 * 🔴 Khai tay mà gõ "máy tự động " thừa dấu cách, hay "MTD", là vai ấy KHÔNG bó gì cả và người
 *    mang nó nhìn thấy sổ của mọi mảng — hỏng đúng theo kiểu không ai nhận ra.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
VHCP_Cfg::write( VHCP_Cfg::VAI, array() );
VHCP_Meta::set( 'seeded_vai_mtd_v1', '' );
VHCP_Cfg::clear_cache();
VHCP_Cfg::cfg_static();   // lượt đọc này chạy seed

$ten_vai = array();
foreach ( VHCP_Cfg::vai_tuy_bien() as $v ) { $ten_vai[ $v['ten'] ] = $v; }
t( '🔴 vai "Kế toán máy tự động" được dựng sẵn', isset( $ten_vai['Kế toán máy tự động'] ), array_keys( $ten_vai ) );
if ( isset( $ten_vai['Kế toán máy tự động'] ) ) {
	teq( 'và nó bó đúng bộ phận Máy tự động', 'Máy tự động', $ten_vai['Kế toán máy tự động']['boPhan'] );
	teq( 'và kế thừa Kế toán cá nhân',        'Kế toán cá nhân', $ten_vai['Kế toán máy tự động']['goc'] );
}
/* ⚠️ Đánh dấu đã seed để anh Thắng còn XOÁ hoặc ĐỔI được. Không đánh dấu thì mỗi lượt nâng cấp
   lại dựng lại một vai anh vừa cố ý bỏ đi. */
VHCP_Cfg::write( VHCP_Cfg::VAI, array() );
VHCP_Cfg::clear_cache();
VHCP_Cfg::cfg_static();
teq( '🔴 anh Thắng xoá vai ấy đi thì lượt sau KHÔNG dựng lại', 0, count( VHCP_Cfg::vai_tuy_bien() ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════ */
if ( $truot ) {
	echo "\n✗ TRƯỢT " . count( $truot ) . " phép (đạt $dat):\n";
	foreach ( $truot as $x ) { echo "  · $x\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $dat phép: vai bó bộ phận chỉ làm việc trong bộ phận của mình.\n";
