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

printf( "%d kiểm tra đạt, %d lỗi\n", $dat, count( $hong ) );
foreach ( $hong as $x ) { echo "  ✗ $x\n"; }
exit( $hong ? 1 : 0 );
