<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * NỐI HAI HỆ: NHÂN SỰ ↔ CHI PHÍ
 *
 * Anh Thắng 13/09/2026, hai câu liền nhau:
 *   *"vậy nếu đẩy từ nhân sự sang, mà nhân viên này trùng với nhân viên tạo trực tiếp trên
 *     trang chi phí thì sao, làm sao để gộp lại"*
 *   *"vì khi nhân viên dùng tk từ nhân sự thì dữ liệu không có"*
 *
 * =============================================================================================
 * 🔴 GỐC RỄ: HAI HỆ KHOÁ NGƯỜI KHÁC NHAU.
 *      bên Nhân sự  khoá là MÃ NV (`UNIQUE KEY ma_nv`)  -> hai người trùng tên vẫn là hai hàng
 *      bên Chi phí  khoá là TÊN                          -> trùng tên là MỘT người, chung sổ đơn
 *    Và trước bản này hai bên còn có HAI KHO PIN rời hẳn nhau, không một dòng mã nào nối —
 *    nên PIN cấp bên Nhân sự gõ vào trang Chi phí chỉ nhận về "PIN không đúng". Đúng câu hai.
 *
 * 🔴 BA VIỆC BẢN NÀY LÀM, VÀ PHÉP NÀO CANH VIỆC NÀO:
 *      1. cột Mã NV bên Chi phí (khoá thứ hai, phân biệt người trùng tên)      -> phần 1, 2
 *      2. một PIN vào cả hai trang — MƯỢN MẬT KHẨU, KHÔNG MƯỢN QUYỀN            -> phần 3
 *      3. màn soát trùng + đổi tên người trên mọi bảng cùng lúc                 -> phần 4, 5
 *
 * Chạy: php tools/test/kiem-noi-nhan-su-chi-phi.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );
vhcc_test_boot( $goc . '/wordpress/vhcp-cham-cong' );

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : var_export( $them, true ) ) ) : '' );
}
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . var_export( $mong, true ) . ')', $mong === $thuc, $thuc ); }

/** Ghi thẳng một hồ sơ vào sổ nhân sự bên trang Chấm công. */
function ho_so( $ma, $ten, $pin = '', $tt = 'Đang làm', $ch = 'FARM PHAN THIẾT', $pb = '' ) {
	global $wpdb;
	$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array(
		'ma_nv' => $ma, 'ho_ten' => $ten, 'cua_hang' => $ch, 'chuc_vu' => 'Nhân viên',
		'trang_thai_lam_viec' => $tt, 'pin_dang_nhap' => $pin, 'phong_ban' => $pb ) );
}
/** Ghi bảng người dùng bên Chi phí. Mỗi phần tử: [tên, pin, vai, cơ sở, maDt, donVi, maNv]. */
function users( $ds ) {
	$rows = array();
	foreach ( $ds as $u ) {
		$rows[] = array( $u[0], $u[1], $u[2], isset( $u[3] ) ? $u[3] : '', '', isset( $u[4] ) ? $u[4] : '',
			isset( $u[7] ) ? $u[7] : '', isset( $u[5] ) ? $u[5] : '', '', isset( $u[6] ) ? $u[6] : '' );
	}
	VHCP_Cfg::write( VHCP_Cfg::USER, $rows );
	VHCP_Cfg::clear_cache();
}
function dang_nhap( $pin ) { delete_transient( 'vhcp_login_fail' ); return VHCP_Auth::login( $pin ); }

/* ═══ 1. CỘT MÃ NV ĐI TRỌN VÒNG: MÀN → MÁY CHỦ → MÀN ═══════════════════════════════
 * ⚠️ Bảng cấu hình lưu mỗi hàng thành một mảng ĐỌC THEO VỊ TRÍ. Thêm một ô mà quên một
 *    đầu là mọi cột sau trượt một nhịp, im lặng. Nên phép đầu tiên đi qua CẢ save_config
 *    lẫn get_users, chứ không ghi thẳng bằng `write()`.
 * ═════════════════════════════════════════════════════════════════════════════════════ */
VHCP_Auth::dat_vai_tro( 'Admin', 'Sếp' );
$r = VHCP_Cfg::save_config( array( 'users' => array(
	array( 'ten' => 'Nguyễn Văn A', 'pin' => '1111', 'vaiTro' => 'Admin',   'coso' => '', 'maNv' => 'NV001', 'donVi' => '' ),
	array( 'ten' => 'Trần Thị B',   'pin' => '2222', 'vaiTro' => 'Nhân viên', 'coso' => 'FARM PHAN THIẾT', 'maNv' => 'NV002', 'donVi' => '' ),
) ) );
t( 'lưu được bảng người dùng có cột Mã NV', ! empty( $r['success'] ), $r );
$u = VHCP_Cfg::get_users();
teq( '🔴 Mã NV đọc lại đúng',          'NV001', $u[0]['maNv'] );
teq( '   và của người thứ hai',        'NV002', $u[1]['maNv'] );
teq( '⚠️ cột ĐƠN VỊ không bị trượt chỗ', '',   $u[0]['donVi'] );
teq( '⚠️ cột CƠ SỞ không bị trượt chỗ', 'FARM PHAN THIẾT', $u[1]['coso'] );
teq( '⚠️ PIN vẫn đúng chỗ',            '2222', $u[1]['pin'] );

/* Dòng CŨ chỉ có chín ô (chưa có Mã NV) vẫn đọc được, ô mã ra rỗng. */
VHCP_Cfg::write( VHCP_Cfg::USER, array( array( 'Người Cũ', '3333', 'Nhân viên', '', '', '', '', '', '' ) ) );
VHCP_Cfg::clear_cache();
$u = VHCP_Cfg::get_users();
teq( '⚠️ dòng cũ chưa có ô Mã NV vẫn đọc được', '', $u[0]['maNv'] );
teq( '   và tên vẫn đúng',            'Người Cũ', $u[0]['ten'] );

/* ═══ 2. HAI DÒNG CÙNG TÊN / CÙNG MÃ NV -> CHỐI KHI LƯU ════════════════════════════
 * 🔴 `user_by_token()` lấy dòng ĐẦU TIÊN khớp tên rồi `break` — dòng thứ hai không bao giờ
 *    tới lượt. Để lọt là khai quyền cho một người mà người ấy không bao giờ nhận được.
 * ═════════════════════════════════════════════════════════════════════════════════════ */
$r = VHCP_Cfg::save_config( array( 'users' => array(
	array( 'ten' => 'Lê Văn C', 'pin' => '4444', 'vaiTro' => 'Nhân viên' ),
	array( 'ten' => 'lê văn c', 'pin' => '5555', 'vaiTro' => 'Quản lý' ),
) ) );
t( '🔴 hai dòng cùng tên (khác hoa thường) bị chối', empty( $r['success'] ), $r );
t( '   và câu chối nói rõ là trùng tên', isset( $r['error'] ) && false !== mb_strpos( $r['error'], 'cùng tên' ), $r );

$r = VHCP_Cfg::save_config( array( 'users' => array(
	array( 'ten' => 'Lê Văn C', 'pin' => '4444', 'vaiTro' => 'Nhân viên', 'maNv' => 'NV009' ),
	array( 'ten' => 'Phạm Thị D', 'pin' => '5555', 'vaiTro' => 'Quản lý', 'maNv' => 'nv009' ),
) ) );
t( '🔴 hai dòng cùng Mã NV bị chối', empty( $r['success'] ), $r );
t( '   và câu chối gọi tên cả hai người',
	isset( $r['error'] ) && false !== mb_strpos( $r['error'], 'Lê Văn C' ) && false !== mb_strpos( $r['error'], 'Phạm Thị D' ), $r );

/* ⚠️ Ô MÃ RỖNG KHÔNG TÍNH LÀ TRÙNG — phần lớn tài khoản chưa khai mã. Gom chúng lại thành
      "mười người trùng mã rỗng" là chặn luôn mọi lượt Lưu, và không ai hiểu vì sao. */
$r = VHCP_Cfg::save_config( array( 'users' => array(
	array( 'ten' => 'Lê Văn C',   'pin' => '4444', 'vaiTro' => 'Nhân viên', 'maNv' => '' ),
	array( 'ten' => 'Phạm Thị D', 'pin' => '5555', 'vaiTro' => 'Quản lý',   'maNv' => '' ),
) ) );
t( '⚠️ hai dòng cùng bỏ TRỐNG ô Mã NV thì vẫn lưu được', ! empty( $r['success'] ), $r );

/* ═══ 3. MỘT PIN VÀO CẢ HAI TRANG ═════════════════════════════════════════════════ */
ho_so( 'NV001', 'Nguyễn Văn A', '7001' );
ho_so( 'NV002', 'Trần Thị B',   '7002' );
ho_so( 'NV003', 'Hoàng Văn E',  '7003' );                       // chưa có tài khoản Chi phí
ho_so( 'NV004', 'Đỗ Thị F',     '7004', 'Đã nghỉ 12/2025' );    // đã nghỉ
ho_so( 'NV005', 'Vũ Văn G',     '7009' );                       // hai hồ sơ cùng PIN
ho_so( 'NV006', 'Bùi Thị H',    '7009' );
ho_so( 'NV007', 'Ngô Văn I',    '7007' );                       // nối được bằng TÊN, chưa khai mã

users( array(
	array( 'Nguyễn Văn A', '1111', 'Admin',     '',                  '', '', 'NV001' ),
	array( 'Trần Thị B',   '2222', 'Nhân viên', 'FARM PHAN THIẾT',   '', '', 'NV002' ),
	array( 'Đỗ Thị F',     '2244', 'Nhân viên', '',                  '', '', 'NV004' ),
	array( 'Vũ Văn G',     '2255', 'Nhân viên', '',                  '', '', 'NV005' ),
	array( 'Ngô Văn I',    '2266', 'Quản lý',   'TÀU TẤN PHÚ',       '', '', ''      ),
) );

$r = dang_nhap( '2222' );
t( '⚠️ PIN cấp ngay trên trang Chi phí vẫn vào như cũ', ! empty( $r['ok'] ), $r );
teq( '   đúng người', 'Trần Thị B', isset( $r['name'] ) ? $r['name'] : '' );

$r = dang_nhap( '7002' );
t( '🔴 PIN bên NHÂN SỰ mở được trang Chi phí', ! empty( $r['ok'] ), $r );
teq( '   nối đúng người qua MÃ NV', 'Trần Thị B', isset( $r['name'] ) ? $r['name'] : '' );
teq( '🔴 VAI lấy từ bảng Chi phí, KHÔNG phải từ Nhân sự', 'Nhân viên', isset( $r['role'] ) ? $r['role'] : '' );
teq( '🔴 CƠ SỞ cũng lấy từ bảng Chi phí', 'FARM PHAN THIẾT', isset( $r['coso'] ) ? $r['coso'] : '' );

$r = dang_nhap( '7007' );
t( '⚠️ chưa khai Mã NV thì nối bằng TÊN', ! empty( $r['ok'] ), $r );
teq( '   đúng người', 'Ngô Văn I', isset( $r['name'] ) ? $r['name'] : '' );
teq( '   và vẫn lấy vai bên Chi phí', 'Quản lý', isset( $r['role'] ) ? $r['role'] : '' );

/* 🔴 CHỐI, VÀ NÓI RÕ VÌ SAO. Trả "PIN không đúng" cho người có PIN đúng là họ gõ lại năm
   lần rồi bị khoá mười phút, trong khi việc cần làm chỉ là khai thêm một dòng. */
$r = dang_nhap( '7003' );
t( '🔴 có PIN bên Nhân sự mà chưa có tài khoản Chi phí -> KHÔNG vào được', empty( $r['ok'] ), $r );
t( '   và câu chối nói rõ là chưa được cấp quyền',
	isset( $r['error'] ) && false !== mb_strpos( $r['error'], 'chưa được cấp quyền' ), $r );
t( '   kèm tên và mã NV để biết phải khai cho ai',
	isset( $r['error'] ) && false !== mb_strpos( $r['error'], 'Hoàng Văn E' )
		&& false !== mb_strpos( $r['error'], 'NV003' ), $r );

$r = dang_nhap( '7004' );
t( '🔴 hồ sơ ĐÃ NGHỈ thì PIN nhân sự không mở được', empty( $r['ok'] ), $r );
t( '   dù người ấy vẫn còn dòng trong bảng Chi phí',
	isset( $r['error'] ) && false !== mb_strpos( $r['error'], 'nghỉ' ), $r );

$r = dang_nhap( '7009' );
t( '🔴 HAI hồ sơ cùng một PIN -> chối, không đoán lấy hàng đầu', empty( $r['ok'] ), $r );
t( '   và nói rõ là PIN đang khai cho nhiều người',
	isset( $r['error'] ) && false !== mb_strpos( $r['error'], '2 người' ), $r );

/* 🔴 THỨ TỰ: BẢNG CHI PHÍ TRƯỚC. PIN 4 chữ số, vài chục người — hai kho trùng PIN là chuyện
   có thật. Tra bên Nhân sự trước thì một PIN trùng sẽ mở nhầm tài khoản của người khác. */
/* ⚠️ NGƯỜI LẠ PHẢI CÓ TÀI KHOẢN CHI PHÍ, không thì cảnh này không chứng minh được gì: người
   không có dòng bên này thì đường vòng cũng chối, nên đảo thứ tự hai nhánh vẫn ra cùng kết
   quả và phép xanh oan. Phá thử bắt đúng chỗ ấy 13/09/2026. */
ho_so( 'NV008', 'Người Lạ', '2222' );
users( array_merge( array( array( 'Người Lạ', '8888', 'Quản lý', '', '', '', 'NV008' ) ), array(
	array( 'Nguyễn Văn A', '1111', 'Admin',     '',                  '', '', 'NV001' ),
	array( 'Trần Thị B',   '2222', 'Nhân viên', 'FARM PHAN THIẾT',   '', '', 'NV002' ),
	array( 'Đỗ Thị F',     '2244', 'Nhân viên', '',                  '', '', 'NV004' ),
	array( 'Vũ Văn G',     '2255', 'Nhân viên', '',                  '', '', 'NV005' ),
	array( 'Ngô Văn I',    '2266', 'Quản lý',   'TÀU TẤN PHÚ',       '', '', ''      ),
) ) );
$r = dang_nhap( '2222' );
teq( '🔴 PIN có ở CẢ HAI kho -> bảng Chi phí thắng', 'Trần Thị B', isset( $r['name'] ) ? $r['name'] : '' );

$r = dang_nhap( '9999' );
t( '⚠️ PIN không có ở đâu cả thì vẫn là câu cũ', empty( $r['ok'] ), $r );

/* ═══ 4. MÀN SOÁT TRÙNG ═══════════════════════════════════════════════════════════ */
global $wpdb;
$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'nhan_vien' ) );
ho_so( 'NV101', 'Nguyễn Văn A' );          // khớp đúng
ho_so( 'NV102', 'Nguyen Van Khong Dau' );  // lệch dấu với bên Chi phí
ho_so( 'NV103', 'Chưa Có Tài Khoản' );     // chỉ có bên Nhân sự
ho_so( 'NV104', 'Lê Văn Trùng' );          // hai hồ sơ trùng tên nhau
ho_so( 'NV105', 'Lê Văn Trùng' );
users( array(
	array( 'Nguyễn Văn A',        '1111', 'Admin',     '', '', '', 'NV101' ),
	array( 'Nguyễn Văn Không Dấu','2222', 'Nhân viên', '', '', '', ''      ),
	array( 'Lê Văn Trùng',        '3333', 'Nhân viên', '', '', '', ''      ),
	array( 'Không Có Hồ Sơ',      '4444', 'Quản lý',   '', '', '', ''      ),
) );
$s = VHCP_Cfg::soat_nhan_su();
t( 'soát chạy được', ! empty( $s['success'] ), $s );
t( '🔴 nhận ra trang Nhân sự có cài', ! empty( $s['coNhanSu'] ), $s );
$n = $s['nhom'];
teq( '🔴 1 cái tên dùng cho hai hồ sơ nhân sự', 1, count( $n['trungTen'] ) );
teq( '   và bày đủ HAI mã NV của nó',           2, count( $n['trungTen'][0]['hoSo'] ) );
teq( '🔴 1 cặp tên LỆCH DẤU giữa hai hệ',       1, count( $n['lech'] ) );
teq( '   tên bên Nhân sự', 'Nguyen Van Khong Dau', $n['lech'][0]['tenNs'] );
teq( '   tên bên Chi phí', 'Nguyễn Văn Không Dấu', $n['lech'][0]['tenCp'] );
teq( '🔴 1 hồ sơ chưa có tài khoản Chi phí',    1, count( $n['thieuCp'] ) );
teq( '   đúng người',      'Chưa Có Tài Khoản', $n['thieuCp'][0]['ten'] );
teq( '🔴 1 tài khoản không có hồ sơ nhân sự',   1, count( $n['thieuNs'] ) );
teq( '   đúng người',        'Không Có Hồ Sơ',  $n['thieuNs'][0]['ten'] );
teq( '✅ 1 người khớp đúng từng chữ',           1, count( $n['khop'] ) );
teq( '   đúng người',          'Nguyễn Văn A',  $n['khop'][0]['ten'] );

/* ⚠️ MỘT DÒNG CHI PHÍ CHỈ ĐƯỢC XẾP VÀO ĐÚNG MỘT NHÓM. "Lê Văn Trùng" vừa trùng tên đồng
   nghiệp vừa có tài khoản bên này — để nó lọt cả vào `thieuNs` là đếm hai lần một người,
   và người đọc màn tưởng mình phải xử lý hai việc. */
$moi_ten = array();
foreach ( array( 'trungTen', 'lech', 'thieuCp', 'thieuNs', 'khop' ) as $g ) {
	foreach ( $n[ $g ] as $x ) {
		$k = isset( $x['tenCp'] ) ? $x['tenCp'] : $x['ten'];
		$moi_ten[ $k ] = ( isset( $moi_ten[ $k ] ) ? $moi_ten[ $k ] : 0 ) + 1;
	}
}
t( '⚠️ mỗi cái tên chỉ nằm ở ĐÚNG MỘT nhóm', 1 === max( $moi_ten ), $moi_ten );

/* Đếm dòng cũ theo tên — con số quyết định có dám đổi tên hay không. */
$wpdb->insert( VHCP_DB::t( 'don' ), array( 'ma_don' => 'D1', 'nguoi_lap' => 'Nguyễn Văn Không Dấu' ) );
$wpdb->insert( VHCP_DB::t( 'don' ), array( 'ma_don' => 'D2', 'nguoi_lap' => 'nguyễn văn không dấu' ) );
$wpdb->insert( VHCP_DB::t( 'so_chi' ), array( 'nguoi_nhap' => 'Nguyễn Văn Không Dấu' ) );
$s = VHCP_Cfg::soat_nhan_su();
teq( '🔴 đếm ĐỦ dòng của cả ba bảng, gom cả hoa lẫn thường', 3, $s['nhom']['lech'][0]['donCp'] );

/* ═══ 4b. LỆCH PHÒNG BAN — NHÂN SỰ QUYẾT ĐỊNH, CHI PHÍ CHỈ ĐỌC ═══════════════════
 * Anh Thắng 13/09/2026: *"quyết định bộ phận do nhân sự quyết định, bên chi phí chỉ biết bộ
 * phận đó có được quyền không thôi, chứ không can thiệp được đổi bộ phận của nhân viên truyền
 * sang"* — và chốt cùng ngày là CHƯA KHOÁ ô bên chi phí vội, chạy song song để đối chiếu.
 * Nhóm `lechPb` chính là bảng đối chiếu ấy.
 * ═════════════════════════════════════════════════════════════════════════════════════ */
$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'nhan_vien' ) );
$wpdb->query( 'DELETE FROM ' . VHCP_DB::t( 'don' ) );
$wpdb->query( 'DELETE FROM ' . VHCP_DB::t( 'so_chi' ) );
ho_so( 'NV201', 'Khớp Phòng Ban',  '', 'Đang làm', 'FARM PHAN THIẾT', 'Kỹ thuật' );
ho_so( 'NV202', 'Lệch Phòng Ban',  '', 'Đang làm', 'FARM PHAN THIẾT', 'Kỹ thuật' );
ho_so( 'NV203', 'Nhân Sự Chưa Khai', '', 'Đang làm', 'FARM PHAN THIẾT', '' );
ho_so( 'NV204', 'Chi Phí Chưa Khai', '', 'Đang làm', 'FARM PHAN THIẾT', 'Marketing' );
users( array(
	array( 'Khớp Phòng Ban',    '1111', 'Nhân viên', '', '', '', 'NV201', 'Kỹ thuật' ),
	array( 'Lệch Phòng Ban',    '2222', 'Nhân viên', '', '', '', 'NV202', 'Marketing' ),
	array( 'Nhân Sự Chưa Khai', '3333', 'Nhân viên', '', '', '', 'NV203', 'Setup' ),
	array( 'Chi Phí Chưa Khai', '4444', 'Nhân viên', '', '', '', 'NV204', '' ),
) );
$s = VHCP_Cfg::soat_nhan_su();
$n = $s['nhom'];
teq( '🔴 đúng 1 người khai phòng ban LỆCH nhau', 1, count( $n['lechPb'] ) );
teq( '   đúng người',        'Lệch Phòng Ban',  $n['lechPb'][0]['ten'] );
teq( '   bày giá trị bên Nhân sự', 'Kỹ thuật',  $n['lechPb'][0]['pbNs'] );
teq( '   và giá trị bên Chi phí',  'Marketing', $n['lechPb'][0]['pbCp'] );
/* ⚠️ MỘT BÊN TRỐNG KHÔNG PHẢI LÀ LỆCH. "Chưa khai" và "khai khác" là hai việc khác hẳn: một
   cái còn phải làm, một cái phải chọn bên nào đúng. Gom chung là bảng đầy dòng không sửa được
   gì, và chỗ lệch thật lẫn mất trong đó. */
teq( '⚠️ bên Nhân sự trống thì KHÔNG tính là lệch', 0,
	count( array_filter( $n['lechPb'], function ( $x ) { return 'Nhân Sự Chưa Khai' === $x['ten']; } ) ) );
teq( '⚠️ bên Chi phí trống cũng vậy', 0,
	count( array_filter( $n['lechPb'], function ( $x ) { return 'Chi Phí Chưa Khai' === $x['ten']; } ) ) );
teq( '🔴 nhưng vẫn ĐẾM RIÊNG: 1 người chưa xếp bên Nhân sự', 1, $s['pbChuaNs'] );
teq( '🔴 và 1 người chưa khai bên Chi phí',                   1, $s['pbChuaCp'] );
/* Nhóm khớp mang theo phòng ban cả hai bên để màn bày ra cạnh nhau. */
$kh = array();
foreach ( $n['khop'] as $x ) { $kh[ $x['ten'] ] = $x; }
teq( '   nhóm khớp mang phòng ban bên Nhân sự', 'Kỹ thuật',  $kh['Khớp Phòng Ban']['pbNs'] );
teq( '   và bên Chi phí',                       'Kỹ thuật',  $kh['Khớp Phòng Ban']['pbCp'] );

/* 🔴 BẢN CHẤM CÔNG CŨ CHƯA CÓ CỘT `phong_ban` — soát vẫn phải chạy, không được ném lỗi SQL.
   Bốn plugin cài độc lập; hỏi thẳng một cột chưa có là mọi lượt soát đều chết. */
$t_ns = VHCC_DB::t( 'nhan_vien' );
$wpdb->exec_raw( "ALTER TABLE $t_ns DROP COLUMN phong_ban" );
$cot_con = (array) $wpdb->get_col( "SHOW COLUMNS FROM $t_ns" );
t( '   (bỏ được cột để dựng cảnh bản cũ)', ! in_array( 'phong_ban', $cot_con, true ), $cot_con );
$s2 = VHCP_Cfg::soat_nhan_su();
t( '⚠️ soát vẫn CHẠY khi bản chấm công chưa có cột phong_ban', ! empty( $s2['success'] ), $s2 );
teq( '   và không ai bị coi là lệch phòng ban', 0, count( $s2['nhom']['lechPb'] ) );
teq( '   mọi người đếm vào "chưa xếp bên Nhân sự"', 4, $s2['pbChuaNs'] );
$wpdb->exec_raw( "ALTER TABLE $t_ns ADD COLUMN phong_ban VARCHAR(120) NOT NULL DEFAULT ''" );

/* ═══ 5. ĐỔI TÊN NGƯỜI TRÊN MỌI BẢNG CÙNG LÚC ════════════════════════════════════ */
/* Dựng lại cảnh của phần 4 — phần 4b đã dọn sạch ba bảng để đếm cho gọn. */
users( array(
	array( 'Nguyễn Văn A',         '1111', 'Admin',     '', '', '', 'NV101' ),
	array( 'Nguyễn Văn Không Dấu', '2222', 'Nhân viên', '', '', '', ''      ),
	array( 'Lê Văn Trùng',         '3333', 'Nhân viên', '', '', '', ''      ),
	array( 'Không Có Hồ Sơ',       '4444', 'Quản lý',   '', '', '', ''      ),
) );
$wpdb->insert( VHCP_DB::t( 'don' ), array( 'ma_don' => 'D1', 'nguoi_lap' => 'Nguyễn Văn Không Dấu' ) );
$wpdb->insert( VHCP_DB::t( 'don' ), array( 'ma_don' => 'D2', 'nguoi_lap' => 'nguyễn văn không dấu' ) );
$wpdb->insert( VHCP_DB::t( 'so_chi' ), array( 'nguoi_nhap' => 'Nguyễn Văn Không Dấu' ) );

$r = VHCP_Cfg::doi_ten_nguoi( 'Nguyễn Văn Không Dấu', 'Nguyen Van Khong Dau' );
t( 'đổi tên chạy được', ! empty( $r['success'] ), $r );
teq( '🔴 dời đủ 3 dòng (2 đơn + 1 sổ chi), kể cả dòng viết thường', 3, $r['doiDong'] );
teq( '   và sửa 1 dòng trong bảng người dùng', 1, $r['sua'] );
$con = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . VHCP_DB::t( 'don' ) . " WHERE nguoi_lap = 'Nguyen Van Khong Dau'" );
teq( '🔴 đơn cũ nay mang tên mới', 2, $con );
$sot = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . VHCP_DB::t( 'don' ) . " WHERE TRIM(LOWER(nguoi_lap)) = 'nguyễn văn không dấu'" );
teq( '🔴 không sót dòng nào mang tên cũ', 0, $sot );
$ten_moi = array();
foreach ( VHCP_Cfg::get_users() as $x ) { $ten_moi[] = $x['ten']; }
t( '   bảng người dùng cũng đổi theo', in_array( 'Nguyen Van Khong Dau', $ten_moi, true ), $ten_moi );

/* 🔴 ĐỔI THÀNH MỘT TÊN ĐÃ CÓ TÀI KHOẢN = GỘP HAI SỔ ĐƠN. Không có đường về, nên phải chối
   lượt đầu và nói ra con số của cả hai bên. */
$r = VHCP_Cfg::doi_ten_nguoi( 'Không Có Hồ Sơ', 'Nguyễn Văn A' );
t( '🔴 đổi sang tên ĐÃ CÓ tài khoản thì chối lượt đầu', empty( $r['success'] ), $r );
t( '   và câu chối dùng đúng chữ "GỘP"',
	isset( $r['error'] ) && false !== mb_strpos( $r['error'], 'GỘP' ), $r );
$so_truoc = count( VHCP_Cfg::get_users() );
$r = VHCP_Cfg::doi_ten_nguoi( 'Không Có Hồ Sơ', 'Nguyễn Văn A', true );
t( '   xác nhận rồi thì gộp được', ! empty( $r['success'] ), $r );
teq( '🔴 gộp là BỎ dòng cũ, giữ dòng đích — không đẻ ra dòng trùng tên', 1, $r['goBo'] );
teq( '   nên bảng người dùng bớt đúng một dòng', $so_truoc - 1, count( VHCP_Cfg::get_users() ) );

/* Thẻ phiên đang mở cũng phải đổi theo — bỏ qua là người ấy còn đăng nhập bằng tên CŨ suốt
   30 ngày, và `user_by_token()` không tìm ra dòng nào khớp nên vai đông cứng từ lúc vào. */
$tok = VHCP_Auth::issue_token( 'Ngô Văn I', 'Quản lý', 'TÀU TẤN PHÚ', '' );
VHCP_Cfg::doi_ten_nguoi( 'Ngô Văn I', 'Ngô Văn Í' );
$ai = VHCP_Auth::user_by_token( $tok );
teq( '🔴 thẻ phiên đang mở đổi tên theo', 'Ngô Văn Í', $ai ? $ai['name'] : '' );
teq( '   nên vai vẫn đọc lại được từ bảng', 'Quản lý', $ai ? $ai['role'] : '' );

$r = VHCP_Cfg::doi_ten_nguoi( 'Ai Đó', 'Ai Đó' );
t( '⚠️ hai tên giống hệt nhau thì chối', empty( $r['success'] ), $r );
$r = VHCP_Cfg::doi_ten_nguoi( '', 'Ai Đó' );
t( '⚠️ thiếu tên cũ thì chối', empty( $r['success'] ), $r );

/* ═══ 6. 🔴 ĐƯỜNG ĐI TỚI HÀM — CỔNG API PHẢI MỞ, VÀ CHỈ CHO ADMIN ═════════════════
 * Mọi phép trên gọi thẳng vào lớp nên chúng chỉ chứng minh CÁI HÀM chạy đúng. Ngoài đời
 * người dùng đi qua cổng API: quên khai vào bảng tra là màn bấm nút không ra gì, mà bộ thử
 * vẫn xanh rờn. Cùng bài học với hai nút sắp xếp bị cái khoá nuốt mất (12/09/2026).
 * ═════════════════════════════════════════════════════════════════════════════════════ */
$api = file_get_contents( $goc . '/wordpress/vhcp-chi-phi/includes/class-vhcp-api.php' );
/* Gỡ chú thích trước khi quét — khối chú thích ngay trên lời khai có nhắc cả hai tên hàm,
   nên quét cả chú thích là phép xanh kể cả khi lời khai đã bị gỡ. Đã cắn hai lần. */
$api_ma = preg_replace( '#/\*.*?\*/#s', '', $api );
$api_ma = preg_replace( '#//[^\n]*#', '', $api_ma );
t( '🔴 soatNhanSu có trong bảng tra hàm',
	false !== strpos( $api_ma, "'soatNhanSu'" ) && false !== strpos( $api_ma, "'soat_nhan_su'" ), null );
t( '🔴 doiTenNguoi có trong bảng tra hàm',
	false !== strpos( $api_ma, "'doiTenNguoi'" ) && false !== strpos( $api_ma, "'doi_ten_nguoi'" ), null );
/* Cả hai phải nằm trong `$admin_only`: một cái bày cả sổ nhân sự, cái kia sửa hàng loạt trên
   tám bảng. Lọt xuống nhóm `$cau_hinh` là kế toán cũng gọi được. */
if ( preg_match( '/\$admin_only\s*=\s*array\((.*?)\);/s', $api_ma, $m_ad ) ) {
	t( '🔴 soatNhanSu chỉ Admin',  false !== strpos( $m_ad[1], "'soatNhanSu'" ),  $m_ad[1] );
	t( '🔴 doiTenNguoi chỉ Admin', false !== strpos( $m_ad[1], "'doiTenNguoi'" ), $m_ad[1] );
} else {
	t( 'đọc được danh sách admin_only', false, 'không khớp regex' );
}
/* 🔴 Và cổng phải TRUYỀN mã NV xuống — quên là `VHCP_Auth::ma_nv()` rỗng ở mọi lượt gọi. */
if ( preg_match( '/VHCP_Auth::dat_vai_tro\(\s*\$role_ht.*?\);/s', $api_ma, $m_dv ) ) {
	t( '🔴 cổng API truyền maNv xuống dat_vai_tro', false !== strpos( $m_dv[0], "'maNv'" ), $m_dv[0] );
} else {
	t( 'đọc được lời gọi dat_vai_tro ở cổng', false, 'không khớp regex' );
}

/* ═══ KẾT ═════════════════════════════════════════════════════════════════════════ */
echo "\n";
if ( $TRUOT ) {
	echo "TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo "  ✗ $x\n"; }
	exit( 1 );
}
echo "ĐẠT: $DAT phép thử\nTất cả phép thử đều đạt.\n";
