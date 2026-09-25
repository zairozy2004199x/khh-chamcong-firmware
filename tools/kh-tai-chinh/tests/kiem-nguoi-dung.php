<?php
/**
 * Kiểm màn hình Người dùng — cấp tài khoản đăng nhập riêng.
 *
 *   php tools/kh-tai-chinh/tests/kiem-nguoi-dung.php
 *
 * Chỗ này cấp quyền vào sổ tiền, nên hai câu hỏi phải trả lời được chắc chắn:
 * ai vào được, và bấm Gỡ xong thì người đó có thật sự hết vào được không.
 */

error_reporting( E_ALL );
set_error_handler( function ( $n, $s, $f, $l ) { throw new ErrorException( $s, 0, $n, $f, $l ); } );
require __DIR__ . '/gia-lap-wp.php';

$dat = 0; $hong = array();
function kiem( $ten, $that, $mong ) {
	global $dat, $hong;
	if ( $that === $mong ) { $dat++; return; }
	$hong[] = sprintf( '%s: nhận %s, mong %s', $ten, var_export( $that, true ), var_export( $mong, true ) );
}
function co( $ten, $html, $chuoi ) { kiem( $ten, strpos( (string) $html, $chuoi ) !== false, true ); }
function dung( $ham ) { ob_start(); try { $ham(); } catch ( Throwable $e ) { ob_end_clean(); throw $e; } return ob_get_clean(); }

KHTC_NguoiDung::dung_vai_tro();
kiem( 'dựng được vai trò riêng', (bool) get_role( KHTC_NguoiDung::VAI_TRO ), true );
kiem( 'vai trò đó mang quyền vào sổ', ! empty( $GLOBALS['khtc_roles'][ KHTC_NguoiDung::VAI_TRO ]['caps'][ KHTC_NguoiDung::QUYEN ] ), true );
kiem( 'nhưng KHÔNG mang quyền sửa trang', isset( $GLOBALS['khtc_roles'][ KHTC_NguoiDung::VAI_TRO ]['caps']['edit_pages'] ), false );
kiem( 'quản trị viên vẫn vào được', user_can( 1, KHTC_NguoiDung::QUYEN ), true );
// Gọi lại nhiều lần không được hỏng gì — nâng cấp plugin chạy lại hàm này.
KHTC_NguoiDung::dung_vai_tro();
kiem( 'gọi lần hai không hỏng', (bool) get_role( KHTC_NguoiDung::VAI_TRO ), true );

// ------------------------------------------------------------- tạo tài khoản
kiem( 'thiếu tên đăng nhập thì từ chối', is_wp_error( KHTC_NguoiDung::them( array( 'ten' => '' ) ) ), true );
kiem( 'thư sai dạng thì từ chối', is_wp_error( KHTC_NguoiDung::them( array( 'ten' => 'a1', 'email' => 'khong-phai-thu' ) ) ), true );
kiem( 'mật khẩu ngắn thì từ chối', is_wp_error( KHTC_NguoiDung::them( array( 'ten' => 'a2', 'mat_khau' => '123' ) ) ), true );

$kq = KHTC_NguoiDung::them( array( 'ten' => 'ketoan.lan', 'hien_thi' => 'Lan', 'email' => 'lan@vi.du' ) );
kiem( 'tạo được tài khoản', is_wp_error( $kq ), false );
kiem( 'mật khẩu tự sinh đủ dài', strlen( $kq['mat_khau'] ) >= 12, true );
kiem( 'và có báo là tự sinh', $kq['tu_sinh'], true );
$lan = $kq['id'];
kiem( 'người mới vào được sổ', user_can( $lan, KHTC_NguoiDung::QUYEN ), true );
kiem( 'nhưng không sửa được trang của website', user_can( $lan, 'edit_pages' ), false );
kiem( 'và không phải quản trị viên', user_can( $lan, 'manage_options' ), false );

// Mật khẩu KHÔNG bao giờ được lọt vào nhật ký.
$nk = $GLOBALS['wpdb']->get_results( 'SELECT tom_tat FROM ' . KHTC_DB::bang( 'nhat_ky' ) );
$ro = 0;
foreach ( $nk as $d ) { if ( false !== strpos( $d->tom_tat, $kq['mat_khau'] ) ) { $ro++; } }
kiem( 'mật khẩu không lọt vào nhật ký', $ro, 0 );
kiem( 'nhưng việc tạo tài khoản thì có ghi', count( array_filter( $nk, fn( $d ) => false !== strpos( $d->tom_tat, 'ketoan.lan' ) ) ) >= 1, true );

// Tên trùng: cấp thêm quyền chứ không tạo mới, và không đổi mật khẩu người ta.
$truoc = count( $GLOBALS['khtc_users'] );
$k2 = KHTC_NguoiDung::them( array( 'ten' => 'ketoan.lan' ) );
kiem( 'tên trùng thì không tạo thêm tài khoản', count( $GLOBALS['khtc_users'] ), $truoc );
kiem( 'mà báo là đã có sẵn', $k2['da_co'], true );
kiem( 'và không trả về mật khẩu nào', $k2['mat_khau'], '' );
kiem( 'thư đã dùng cho người khác thì từ chối', is_wp_error( KHTC_NguoiDung::them( array( 'ten' => 'nguoi.khac', 'email' => 'lan@vi.du' ) ) ), true );

// --------------------------------------------------------------- đổi mật khẩu
$cu_mk = $GLOBALS['khtc_users'][ $lan ]->mat_khau;
$d = KHTC_NguoiDung::doi_mat_khau( $lan, '' );
kiem( 'đổi mật khẩu chạy được', is_wp_error( $d ), false );
kiem( 'mật khẩu thật sự đổi', $GLOBALS['khtc_users'][ $lan ]->mat_khau !== $cu_mk, true );
kiem( 'mật khẩu quá ngắn bị từ chối', is_wp_error( KHTC_NguoiDung::doi_mat_khau( $lan, 'abc' ) ), true );

// ------------------------------------------------------------------ gỡ quyền
kiem( 'không tự gỡ quyền của chính mình', is_wp_error( KHTC_NguoiDung::go( get_current_user_id() ) ), true );
kiem( 'không gỡ được quản trị viên website', is_wp_error( KHTC_NguoiDung::go( 1 ) ), true );
kiem( 'tài khoản không có thì báo lỗi', is_wp_error( KHTC_NguoiDung::go( 9999 ) ), true );

kiem( 'gỡ được người thường', KHTC_NguoiDung::go( $lan ), true );
// Câu hỏi thật sự: bấm Gỡ xong thì họ CÓ CÒN VÀO ĐƯỢC KHÔNG.
kiem( 'gỡ xong là hết vào được sổ', user_can( $lan, KHTC_NguoiDung::QUYEN ), false );
kiem( 'nhưng tài khoản vẫn còn, không bị xoá', (bool) get_user_by( 'id', $lan ), true );
kiem( 'và vẫn còn một vai trò để đăng nhập website', ! empty( $GLOBALS['khtc_users'][ $lan ]->roles ), true );

// Người mang vai trò CŨ (Editor, có edit_pages) — bộ lọc bù quyền cho họ vào
// được, nên nút Gỡ phải chặn được cả đường đó, nếu không bấm xong vẫn vào.
$bt = wp_insert_user( array( 'user_login' => 'bientap', 'user_pass' => 'x1234567', 'user_email' => 'bt@vi.du', 'role' => 'editor' ) );
kiem( 'người vai trò cũ vào được nhờ bù quyền', user_can( $bt, KHTC_NguoiDung::QUYEN ), true );
kiem( 'gỡ được họ', KHTC_NguoiDung::go( $bt ), true );
kiem( 'và họ hết vào được, dù vẫn còn edit_pages', user_can( $bt, KHTC_NguoiDung::QUYEN ), false );
kiem( 'quyền cũ của họ trên website không bị đụng', user_can( $bt, 'edit_pages' ), true );

// ---------------------------------------------------- ai được quản lý màn hình
kiem( 'quản trị viên quản lý được', KHTC_NguoiDung::duoc_quan_ly(), true );
$GLOBALS['khtc_cam'] = array( 'create_users' => true );
kiem( 'không có quyền tạo người dùng thì không quản lý được', KHTC_NguoiDung::duoc_quan_ly(), false );
kiem( 'và mọi hàm ghi đều từ chối', is_wp_error( KHTC_NguoiDung::them( array( 'ten' => 'len.quyen' ) ) ), true );
kiem( 'gỡ cũng từ chối', is_wp_error( KHTC_NguoiDung::go( $lan ) ), true );
kiem( 'đổi mật khẩu cũng từ chối', is_wp_error( KHTC_NguoiDung::doi_mat_khau( $lan, 'matkhaudai123' ) ), true );
// Màn hình phải nói thẳng chứ không hiện bảng rỗng như thể không có ai.
$h = dung( fn() => KHTC_Trang::nguoi_dung() );
kiem( 'màn hình từ chối rõ ràng', false !== strpos( $h, 'Chỉ quản trị viên' ), true );
kiem( 'và không lộ danh sách tài khoản', false !== strpos( $h, '<code>admin</code>' ), false );
// Mục menu cũng phải biến mất, không để bấm vào rồi mới bị chặn.
kiem( 'menu không còn mục Người dùng', in_array( 'nguoi-dung', KHTC_Web::nhom()['Hệ thống'], true ), false );
$GLOBALS['khtc_cam'] = array();
kiem( 'có quyền thì menu hiện lại', in_array( 'nguoi-dung', KHTC_Web::nhom()['Hệ thống'], true ), true );

// ------------------------------------------------------------ dựng màn hình
$h = dung( fn() => KHTC_Trang::nguoi_dung() );
kiem( 'màn hình dựng ra có bảng người dùng', false !== strpos( $h, 'Đang vào được sổ' ), true );
kiem( 'có ô thêm người', false !== strpos( $h, 'khtc_them_nd' ), true );
// So trong ô <code> của bảng, không so cả trang: chữ "ketoan.lan" còn nằm ở
// placeholder của ô nhập tên, so cả trang là bài kiểm tự lừa mình.
kiem( 'người đã gỡ không còn trong bảng', false !== strpos( $h, '<code>ketoan.lan</code>' ), false );
kiem( 'nhưng admin thì có trong bảng', false !== strpos( $h, '<code>admin</code>' ), true );
kiem( 'nguoi-dung là đường dẫn hợp lệ', isset( KHTC_Web::man_hinh()['nguoi-dung'] ), true );


// ---------------------------------------------------------------- giới hạn một người một pháp nhân
$GLOBALS['khtc_user_meta'] = array();
KHTC_Cty::chon( 'kh_moi' );
kiem( 'không giới hạn: chọn KH Mới thì ra KH Mới', KHTC_Cty::dang_chon(), 'kh_moi' );
update_user_meta( 1, 'khtc_chi_cty', 'kh_cu' );
kiem( 'bị giới hạn KH Cũ: luôn ra KH Cũ', KHTC_Cty::dang_chon(), 'kh_cu' );
KHTC_Cty::chon( 'kh_moi' );
kiem( 'bấm đổi không ăn', KHTC_Cty::dang_chon(), 'kh_cu' );
KHTC_Cty::chon( 'kh_moi', true );
kiem( 'máy ép tạm (nạp lô) vẫn chuyển được', KHTC_Cty::dang_chon(), 'kh_moi' );
KHTC_Cty::thoi_ep();
kiem( 'tắt ép về lại giới hạn', KHTC_Cty::dang_chon(), 'kh_cu' );
$h_dau = dung( fn() => KHTC_UI::dau_trang( 'Thử' ) );
kiem( 'đầu trang không có nút đổi khi bị giới hạn', strpos( $h_dau, 'name="khtc_cty"' ), false );
kiem( 'mà nói rõ chỉ vào sổ KH Cũ', false !== strpos( $h_dau, 'chỉ vào sổ KH Cũ' ), true );
// nạp lô: tệp của bên kia bị bỏ, không ghi
$nh_kia = null;
KHTC_Cty::chon( 'kh_moi', true );
$nh_kia = KHTC_NganHang::them( array( 'ten' => 'MB bên Mới', 'so_tk' => '02865168', 'so_du_dau' => 0, 'ngay_dau' => '2026-08-01' ) );
KHTC_Cty::thoi_ep();
$qr_kia = "STT\tThời gian TT\tSố tiền đến (VND)\tSố tiền đi (VND)\tLoại\tTrạng thái\tMã tham chiếu\tMã đơn hàng\tMã điểm bán\tMã cửa hàng\tTài khoản nhận\tThời gian tạo\tNội dung TT\n1\t10-09-2026 09:00:00\t120000\t0\tGiao dịch đến\tThành công\tFTGH1\tX\tVVB\tQRA\t02865168 - MB\t10-09-2026\tQR\n";
$lo = KHTC_NapLo::nap( array( array( 'ten' => 'qr-kia.xlsx', 'trang' => array( array( 'ten' => 'S', 'van_ban' => $qr_kia, 'so_dong' => 2 ) ) ) ) );
kiem( 'nạp lô: tệp của bên bị cấm → bỏ', $lo['ket_qua'][0]['bo'], true );
co( 'và nói vì sao', $lo['ket_qua'][0]['ghi_chu'], 'chỉ được vào sổ KH Cũ' );
kiem( 'không ghi dòng nào sang bên kia', (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . KHTC_DB::bang( 'giao_dich' ) . ' WHERE ngan_hang_id=' . (int) $nh_kia ), 0 );
// id của bên kia gửi thẳng từ form → không đụng được
KHTC_Cty::chon( 'kh_moi', true );
$diem_kia = KHTC_Diem::them( array( 'ma_cua_hang' => 'KIA1', 'ten_diem' => 'Điểm bên Mới' ) );
KHTC_Cty::thoi_ep();
kiem( 'không thấy tài khoản bên kia theo id', KHTC_NganHang::mot( $nh_kia ), null );
kiem( 'không xoá được điểm bên kia theo id', is_wp_error( KHTC_Diem::xoa( $diem_kia ) ), true );
kiem( 'không ghi được giao dịch vào tài khoản bên kia', is_wp_error( KHTC_GiaoDich::them( array( 'ngan_hang_id' => $nh_kia, 'ngay' => '10/09/2026', 'so_tien' => 1000, 'dien_giai' => 'x' ) ) ), true );
$h_sl = dung( fn() => KHTC_Trang::sao_luu() );
co( 'bị giới hạn thì không vào Sao lưu', $h_sl, 'chỉ người xem được cả hai mới dùng' );
kiem( 'và không thấy nút hoán đổi', strpos( $h_sl, 'khtc_hoan_doi' ), false );
delete_user_meta( 1, 'khtc_chi_cty' );
KHTC_Cty::chon( 'kh_moi' );
kiem( 'bỏ giới hạn: điểm bên kia vẫn còn nguyên', null !== KHTC_Diem::mot( $diem_kia ), true );
KHTC_Cty::chon( 'kh_cu' );
kiem( 'bỏ giới hạn thì lại chọn được', ( KHTC_Cty::chon( 'kh_moi' ) || true ) && KHTC_Cty::dang_chon() === 'kh_moi', true );
KHTC_Cty::chon( 'kh_cu' );
// đặt giới hạn cho người khác qua API + màn hình
kiem( 'không tự giới hạn mình', is_wp_error( KHTC_NguoiDung::dat_chi_cty( 1, 'kh_cu' ) ), true );
kiem( 'pháp nhân lạ bị chặn', is_wp_error( KHTC_NguoiDung::dat_chi_cty( 2, 'kh_xxx' ) ), true );
$GLOBALS['khtc_cam'] = array();   // khtc_cam là danh sách quyền BỊ CẤM; rỗng = quản trị đủ quyền
$tao_hoa = KHTC_NguoiDung::them( array( 'ten' => 'ketoan.hoa', 'hien_thi' => 'Hoà' ) );   // người còn quyền (lan đã bị gỡ ở trên)
$id_lan = (int) get_user_by( 'login', 'ketoan.hoa' )->ID;
$_POST = array( 'khtc_chi_cty' => $id_lan, 'cty_' . $id_lan => 'kh_moi' );
$h = dung( fn() => KHTC_Trang::nguoi_dung() );
$_POST = array();
co( 'màn hình: báo đã đổi phạm vi', $h, 'Đã đổi phạm vi xem' );
co( 'màn hình: có cột Được xem', $h, '<th>Được xem</th>' );
co( 'màn hình: chọn sẵn chỉ KH Mới cho người đó', $h, 'value="kh_moi" selected' );
kiem( 'giới hạn được lưu cho đúng người', KHTC_Cty::chi_duoc( $id_lan ), 'kh_moi' );

// ---------------------------------------------------------------- trang đăng nhập riêng
$h = dung( fn() => KHTC_Web::trang_dang_nhap( '' ) );
co( 'trang đăng nhập có ô tên', $h, 'name="ten"' );
co( 'và ô mật khẩu', $h, 'type="password"' );
co( 'và nút vào sổ', $h, 'name="khtc_dang_nhap"' );
co( 'không lộ đường wp-login', $h, 'Người dùng' );
kiem( 'không có link wp-login', strpos( $h, 'wp-login' ), false );
$GLOBALS['khtc_transient'] = array(); $_SERVER['REMOTE_ADDR'] = '10.0.0.9';
kiem( 'chưa khoá', KHTC_Web::dang_bi_khoa(), false );
for ( $i = 0; $i < 5; $i++ ) { KHTC_Web::ghi_sai( 'ketoan.hoa' ); }
kiem( '5 lần sai → khoá tên đó', KHTC_Web::dang_bi_khoa( 'ketoan.hoa' ), true );
kiem( 'người khác cùng IP (cùng văn phòng) vẫn vào được', KHTC_Web::dang_bi_khoa( 'ketoan.lan' ), false );
KHTC_Web::xoa_sai( 'ketoan.hoa' );
kiem( 'đăng nhập đúng thì mở khoá', KHTC_Web::dang_bi_khoa( 'ketoan.hoa' ), false );
// gửi form sai → câu chung chung, không nói tên có tồn tại
$_POST = array( 'khtc_dang_nhap' => 1, '_wpnonce' => 'x', 'ten' => 'khongco', 'mk' => 'sai' );
$h = dung( fn() => KHTC_Web::dang_nhap( '' ) );
co( 'sai → câu chung chung', $h, 'Sai tên đăng nhập hoặc mật khẩu' );
kiem( 'không tiết lộ tên không tồn tại', strpos( $h, 'không tồn tại' ), false );
// đúng nhưng chưa có quyền → thoát và báo
wp_insert_user( array( 'user_login' => 'thuong', 'user_pass' => 'x1234567', 'user_email' => 'thuong@vi.du', 'role' => 'subscriber' ) );
$_POST = array( 'khtc_dang_nhap' => 1, '_wpnonce' => 'x', 'ten' => 'thuong', 'mk' => 'dung' );
$GLOBALS['khtc_logged_out'] = false;
$h = dung( fn() => KHTC_Web::dang_nhap( '' ) );
co( 'đúng mật khẩu nhưng không có quyền → báo chưa cấp', $h, 'chưa được cấp quyền' );
kiem( 'và bị đăng xuất ngay', $GLOBALS['khtc_logged_out'], true );
$_POST = array();

printf( "%d kiểm tra đạt, %d lỗi\n", $dat, count( $hong ) );
foreach ( $hong as $x ) { echo "  ✗ $x\n"; }
exit( $hong ? 1 : 0 );
