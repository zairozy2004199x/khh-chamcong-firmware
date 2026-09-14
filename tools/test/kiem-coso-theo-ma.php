<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * Ô CƠ SỞ KHAI BẰNG MÃ ĐƠN VỊ VẪN PHẢI RA ĐÚNG CƠ SỞ.
 *
 * Anh Thắng 14/09/2026, tài khoản "Ung Nguyễn Thùy Dương · Nhân viên · TUTU_BD": *"vẫn không xem
 * được đơn cũ của bạn NV cùng cơ sở làm trước"*. Ô Cơ sở khai `TUTU_BD` — đó là MÃ ĐƠN VỊ, còn
 * đơn thì ghi TÊN THƯỜNG GỌI. Chốt phạm vi so tên với tên nên trượt sạch, và trượt IM LẶNG.
 *
 * =============================================================================================
 * 🔴 TRA SỔ, KHÔNG PHẢI NỚI TAY. Đây là ranh giới của cả tệp này:
 *      · So khớp MỜ (khớp một phần, bỏ dấu) thì "Tân An" trúng cả "VR Tân An" lẫn "TuTu Tân An"
 *        — mở sổ tiền của gian khác cho người không phụ trách.
 *      · Tra SỔ thì mã phải CÓ THẬT trong danh mục, và mỗi mã dẫn tới ĐÚNG MỘT tên. Không có
 *        thì trả nguyên chuỗi, chốt vẫn chối như cũ.
 *
 * 🔴 MÃ TRÙNG NHAU THÌ KHÔNG DỊCH. Hai cơ sở lỡ khai chung một mã thì dịch sang cái nào cũng là
 *    đoán — mà đoán ở chốt phân quyền là mở nhầm cửa.
 *
 * Chạy: php tools/test/kiem-coso-theo-ma.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$GOC = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
vhcp_test_boot( $GOC . '/wordpress/vhcp-chi-phi' );

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : var_export( $them, true ) ) ) : '' );
}
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . var_export( $mong, true ) . ')', $mong === $thuc, $thuc ); }

/* Danh mục giả: ba cơ sở, mỗi cơ sở một mã đơn vị và một tên MISA. */
class VHCP_Cfg_Gia {
	public static $ds = array();
	public static function cfg_static() { return array( 'coso' => self::$ds ); }
}
VHCP_Cfg_Gia::$ds = array(
	array( 'ten' => 'TUTU BÌNH DƯƠNG', 'maDonVi' => 'TUTU_BD', 'tenMisa' => 'TuTu BD' ),
	array( 'ten' => 'TUTU TÂN AN',     'maDonVi' => 'TUTU_TA', 'tenMisa' => '' ),
	array( 'ten' => 'VR TÂN AN',       'maDonVi' => 'VR_TA',   'tenMisa' => '' ),
);

/* Lớp Auth thật, nhưng trỏ sang danh mục giả: chép nguyên hai hàm là dựng bản thứ hai của luật,
   nên ở đây chỉ đổi NGUỒN danh mục bằng cách nạp lớp thật rồi ghi đè sổ nhớ. */
require_once $GOC . '/wordpress/vhcp-chi-phi/includes/class-vhcp-auth.php';
$ref = new ReflectionClass( 'VHCP_Auth' );
$p   = $ref->getProperty( 'so_cs' );
$p->setAccessible( true );
$p->setValue( null, array(
	array( 'ten' => 'TUTU BÌNH DƯƠNG', 'ma' => 'TUTU_BD', 'tenMisa' => 'TuTu BD' ),
	array( 'ten' => 'TUTU TÂN AN',     'ma' => 'TUTU_TA', 'tenMisa' => '' ),
	array( 'ten' => 'VR TÂN AN',       'ma' => 'VR_TA',   'tenMisa' => '' ),
) );

/* ═══ 1. DỊCH MÃ SANG TÊN ══════════════════════════════════════════════════════ */
teq( '🔴 mã đơn vị ra đúng tên cơ sở', 'TUTU BÌNH DƯƠNG', VHCP_Auth::doi_ma_sang_ten( 'TUTU_BD' ) );
teq( 'và không phân biệt hoa thường',  'TUTU BÌNH DƯƠNG', VHCP_Auth::doi_ma_sang_ten( 'tutu_bd' ) );
teq( 'khoảng trắng thừa cũng bỏ qua',  'TUTU BÌNH DƯƠNG', VHCP_Auth::doi_ma_sang_ten( '  TUTU_BD  ' ) );
teq( '🔴 tên theo MISA cũng dịch được', 'TUTU BÌNH DƯƠNG', VHCP_Auth::doi_ma_sang_ten( 'TuTu BD' ) );
/* Tên thường gọi thì giữ nguyên — đường đi thẳng, không tra gì thêm. */
teq( 'tên thường gọi giữ nguyên', 'TUTU TÂN AN', VHCP_Auth::doi_ma_sang_ten( 'TUTU TÂN AN' ) );
teq( 'và chuẩn lại đúng chữ trong danh mục', 'VR TÂN AN', VHCP_Auth::doi_ma_sang_ten( 'vr tân an' ) );

/* ═══ 2. KHÔNG DỊCH THÌ KHÔNG ĐOÁN ═════════════════════════════════════════════ */
/* 🔴 KHÔNG CÓ TRONG SỔ -> TRẢ NGUYÊN CHUỖI, chốt vẫn chối. Đây là vế giữ cho tệp này là "tra
   sổ" chứ không phải "nới tay": thứ không tra được thì không mở cửa. */
teq( '🔴 chuỗi lạ không dịch, trả nguyên', 'KHÔNG CÓ', VHCP_Auth::doi_ma_sang_ten( 'KHÔNG CÓ' ) );
/* 🔴 KHÔNG KHỚP MỘT PHẦN. "Tân An" là khúc đuôi của hai cơ sở — dịch bừa là mở sổ gian khác. */
teq( '🔴 không khớp một phần tên', 'TÂN AN', VHCP_Auth::doi_ma_sang_ten( 'TÂN AN' ) );
teq( '🔴 không khớp khúc đầu của mã', 'TUTU', VHCP_Auth::doi_ma_sang_ten( 'TUTU' ) );
/* 🔴 KHÚC CON CHỈ TRÚNG MỘT MÃ CŨNG KHÔNG ĐƯỢC DỊCH. Phép trên chưa đủ: "TUTU" là khúc con của
   HAI mã nên cách nào cũng ra "không dịch" — bản nháp đục `===` thành `mb_strpos` mà bài vẫn
   xanh. Chuỗi dưới đây chỉ nằm trong ĐÚNG MỘT mã, nên nó phân biệt được hai lối. */
teq( '🔴 khúc con trúng đúng một mã -> VẪN không dịch', 'R_TA', VHCP_Auth::doi_ma_sang_ten( 'R_TA' ) );
teq( '🔴 và khúc con của tên MISA cũng vậy', 'uTu B', VHCP_Auth::doi_ma_sang_ten( 'uTu B' ) );
teq( 'chuỗi rỗng trả rỗng', '', VHCP_Auth::doi_ma_sang_ten( '' ) );

/* ⚠️ MÃ TRÙNG NHAU THÌ KHÔNG DỊCH — đoán ở chốt phân quyền là mở nhầm cửa. */
$p->setValue( null, array(
	array( 'ten' => 'CƠ SỞ A', 'ma' => 'DUP', 'tenMisa' => '' ),
	array( 'ten' => 'CƠ SỞ B', 'ma' => 'DUP', 'tenMisa' => '' ),
) );
teq( '⚠️ hai cơ sở chung một mã -> KHÔNG dịch', 'DUP', VHCP_Auth::doi_ma_sang_ten( 'DUP' ) );

/* ⚠️ DANH MỤC RỖNG (bản mới cài, bảng chưa gieo) -> trả nguyên chuỗi, không biến ô đang khai
   đúng thành rỗng. */
$p->setValue( null, array() );
teq( '⚠️ danh mục rỗng thì trả nguyên chuỗi', 'TUTU_BD', VHCP_Auth::doi_ma_sang_ten( 'TUTU_BD' ) );

/* ═══ 3. CHỐT PHẠM VI ĐỌC QUA BẢN DỊCH ═════════════════════════════════════════ */
$p->setValue( null, array(
	array( 'ten' => 'TUTU BÌNH DƯƠNG', 'ma' => 'TUTU_BD', 'tenMisa' => 'TuTu BD' ),
	array( 'ten' => 'TUTU TÂN AN',     'ma' => 'TUTU_TA', 'tenMisa' => '' ),
) );
VHCP_Auth::dat_vai_tro( 'Nhân viên', 'Thuỳ Dương', 'TUTU_BD', '', '' );
t( '🔴 khai bằng MÃ vẫn trông được cơ sở ấy', VHCP_Auth::trong_coso( 'TUTU BÌNH DƯƠNG' ), '' );
t( '🔴 nhưng KHÔNG trông sang cơ sở khác',   ! VHCP_Auth::trong_coso( 'TUTU TÂN AN' ), '' );
/* 🔴 ĐÚNG CA CỦA ANH THẮNG: đơn của người làm trước, cùng cơ sở, khai bằng mã. */
t( '🔴 đơn người khác cùng cơ sở -> THẤY',
	VHCP_Auth::trong_tam( 'Bạn Nghỉ Việc', 'TUTU BÌNH DƯƠNG' ), '' );
t( '🔴 đơn người khác khác cơ sở -> KHÔNG thấy',
	! VHCP_Auth::trong_tam( 'Bạn Nghỉ Việc', 'TUTU TÂN AN' ), '' );
t( '⚠️ đơn của chính mình thì luôn thấy, dù cơ sở nào',
	VHCP_Auth::trong_tam( 'Thuỳ Dương', 'CƠ SỞ LẠ' ), '' );

/* ⚠️ MÀN PHẢI NHẬN GIÁ TRỊ ĐÃ DỊCH — nó lọc danh sách trước khi hỏi máy chủ, hai bên dịch riêng
   là sớm muộn lệch, và lệch ở đây nghĩa là màn giấu đơn mà máy chủ vẫn cho xem. */
teq( '🔴 chuỗi gửi xuống màn đã dịch sẵn',
	'TUTU BÌNH DƯƠNG, TUTU TÂN AN', VHCP_Auth::coso_hien( 'TUTU_BD, TUTU_TA' ) );
teq( '⚠️ và giữ nguyên phần không dịch được', 'TUTU BÌNH DƯƠNG, LẠ HOẮC',
	VHCP_Auth::coso_hien( 'TUTU_BD, LẠ HOẮC' ) );

/* ⚠️ Đọc `cfg_static()` chứ không `get_config()`: hàm này chạy trên MỌI lượt lọc đơn, mà
   `get_config()` kéo cả bảng chi phí về chỉ để dựng danh sách đối tượng. */
$auth_ma = preg_replace( '#/\*[\s\S]*?\*/#', ' ',
	file_get_contents( $GOC . '/wordpress/vhcp-chi-phi/includes/class-vhcp-auth.php' ) );
t( '⚠️ đọc danh mục bằng cfg_static(), không bằng get_config()',
	false !== strpos( $auth_ma, "'cfg_static'" ) && false === strpos( $auth_ma, "'get_config'" ), '' );
t( '⚠️ và nhớ tạm trong một lượt gọi, không đọc lại từng lần',
	false !== strpos( $auth_ma, 'if ( null !== self::$so_cs ) { return self::$so_cs; }' ), '' );
/* 🔴 MỌI ĐIỂM TRẢ `coso` XUỐNG MÀN ĐỀU PHẢI QUA `coso_hien()`. Màn lọc danh sách đơn trước khi
   hỏi máy chủ, nên một điểm sót là màn giấu đơn mà máy chủ vẫn cho xem — và không câu lỗi nào.
   Ba điểm: đăng nhập bằng PIN · đọc lại thẻ phiên · đăng nhập SSO. */
$diem = preg_match_all( "#'coso'\s*=>\s*self::coso_hien\(#", $auth_ma );
t( '🔴 cả ba điểm trả coso xuống màn đều dịch sẵn', 3 === $diem, $diem );
/* ⚠️ Và không còn điểm nào trả thẳng chuỗi thô của sổ người dùng. */
t( '⚠️ không còn chỗ nào trả coso thô cho màn',
	! preg_match( '#.coso.\s*=>\s*\\$u\[.coso.\]#', $auth_ma ), '' );

/* ═══ 4. CÙNG Ô CƠ SỞ TRONG CẤU HÌNH = CÙNG CHỖ LÀM ════════════════════════════
 * Anh Thắng 14/09/2026: *"2 nhân viên cùng cơ sở thì làm việc như nhau, nhìn thấy nội dung như
 * nhau, chức năng quyền hạn như nhau"*, *"có quyền làm tiếp đơn cũ của người cũ"*, và chốt:
 * *"cấu hình nội bộ chi phí mà, không liên quan bên ngoài"* · *"phân cơ sở thì toàn quyền"*.
 *
 * 🔴 ĐÂY LÀ GỐC RỄ CỦA CẢ CHUỖI HỎI NGÀY 14/09. Hai vế cũ chỉ so ô Cơ sở của NGƯỜI ĐANG XEM với
 *    chuỗi cơ sở ghi TRÊN ĐƠN. Nên hai người cùng được phân `TUTU_BD` vẫn không thấy nhau, vì
 *    dòng chi ghi "TÀU BÌNH DƯƠNG" — hai chuỗi khác nhau cho cùng một chỗ làm. Bạn mới nhận
 *    việc mở trang thấy trắng, và không ai hiểu vì sao.
 * ═══════════════════════════════════════════════════════════════════════════════ */
/* ⚠️ BƠM SỔ NGƯỜI DÙNG VÀO CHÍNH LỚP THẬT, không dựng lớp giả: `VHCP_Cfg::get_users()` đọc
   `cfg_static()`, mà hàm ấy có bộ nhớ tạm — đặt thẳng vào đó là cả lối đi thật được dùng, kể
   cả chỗ đọc khoá `coso`. Dựng lớp giả thì phép kiểm chỉ chứng minh lớp giả chạy đúng. */
require_once $GOC . '/wordpress/vhcp-chi-phi/includes/class-vhcp-cfg.php';
$ref_cfg = new ReflectionClass( 'VHCP_Cfg' );
$p_memo  = $ref_cfg->getProperty( 'memo' );
$p_memo->setAccessible( true );
function nap_users( $p_memo, $ds ) {
	$p_memo->setValue( null, array( 'users' => $ds, 'coso' => array() ) );
}
nap_users( $p_memo, array(
	array( 'ten' => 'Trương Thanh Lâm', 'coso' => 'TUTU_BD' ),
	array( 'ten' => 'Thuỳ Dương',       'coso' => 'TUTU_BD' ),
	array( 'ten' => 'Người Gian Khác',  'coso' => 'VR_TA' ),
	array( 'ten' => 'Chưa Khai',        'coso' => '' ),
) );
$p->setValue( null, array() );   // danh mục rỗng: cấu hình nội bộ, không liên quan bên ngoài
VHCP_Auth::dat_vai_tro( 'Nhân viên', 'Thuỳ Dương', 'TUTU_BD', '', '' );

/* 🔴 ĐÚNG CA CỦA ANH THẮNG: đơn của người làm trước, ghi cơ sở bằng TÊN ĐẦY ĐỦ, trong khi cả
   hai tài khoản khai bằng MÃ. Vế cũ trượt; vế mới bắt được vì hỏi "người lập có cùng ô Cơ sở
   với mình không". */
t( '🔴 đơn người cùng ô Cơ sở -> THẤY, dù đơn ghi tên khác hẳn',
	VHCP_Auth::trong_tam( 'Trương Thanh Lâm', 'TÀU BÌNH DƯƠNG' ), '' );
t( '🔴 và thấy cả khi đơn chưa có dòng chi nào (cơ sở rỗng)',
	VHCP_Auth::trong_tam( 'Trương Thanh Lâm', '' ), '' );
/* 🔴 NHƯNG KHÔNG MỞ SANG GIAN KHÁC — đây là vế giữ cho "toàn quyền" không thành "thấy hết". */
t( '🔴 người gian khác -> KHÔNG thấy',
	! VHCP_Auth::trong_tam( 'Người Gian Khác', 'TÀU BÌNH DƯƠNG' ), '' );
t( '🔴 người lạ không có trong sổ -> KHÔNG thấy',
	! VHCP_Auth::trong_tam( 'Ai Đó Lạ', 'TÀU BÌNH DƯƠNG' ), '' );
/* ⚠️ RỖNG GẶP RỖNG KHÔNG PHẢI LÀ CÙNG CHỖ LÀM. Coi là cùng thì mọi người quên khai bỗng thấy
   đơn của nhau — mở toang bằng đúng cái ô người ta quên điền. */
t( '⚠️ họ chưa được phân cơ sở -> KHÔNG ghép',
	! VHCP_Auth::trong_tam( 'Chưa Khai', 'TÀU BÌNH DƯƠNG' ), '' );
VHCP_Auth::dat_vai_tro( 'Nhân viên', 'Chưa Khai', '', '', '' );
t( '⚠️ và mình chưa được phân cơ sở thì cũng không ghép với ai',
	! VHCP_Auth::trong_tam( 'Trương Thanh Lâm', 'TÀU BÌNH DƯƠNG' ), '' );
t( '⚠️ nhưng đơn của CHÍNH MÌNH thì vẫn thấy',
	VHCP_Auth::trong_tam( 'Chưa Khai', 'GIAN NÀO CŨNG ĐƯỢC' ), '' );
/* 🔴 CẢ HAI CÙNG RỖNG CŨNG KHÔNG PHẢI CÙNG CHỖ LÀM. Phép trên chưa đủ: hai ca ấy còn một bên
   có cơ sở nên lối nào cũng ra "không ghép" — bản nháp đục chốt thành "rỗng gặp rỗng = cùng
   chỗ" mà bài vẫn xanh. Ca dưới đây là ca duy nhất phân biệt được, và nó là ca nguy nhất: mọi
   người quên khai bỗng thấy đơn của nhau, mở toang bằng đúng cái ô người ta quên điền. */
nap_users( $p_memo, array(
	array( 'ten' => 'Chưa Khai',   'coso' => '' ),
	array( 'ten' => 'Chưa Khai 2', 'coso' => '' ),
) );
VHCP_Auth::dat_vai_tro( 'Nhân viên', 'Chưa Khai', '', '', '' );
t( '🔴 hai người CÙNG rỗng -> vẫn KHÔNG thấy đơn của nhau',
	! VHCP_Auth::trong_tam( 'Chưa Khai 2', '' ), '' );

/* ⚠️ Chỉ cần CHẠM một cơ sở chung: người phụ trách hai gian, người kia một gian. */
nap_users( $p_memo, array(
	array( 'ten' => 'Trương Thanh Lâm', 'coso' => 'TUTU_BD' ),
	array( 'ten' => 'Thuỳ Dương',       'coso' => 'TUTU_BD' ),
	array( 'ten' => 'Hai Gian',         'coso' => 'VR_TA, TUTU_BD' ),
) );
VHCP_Auth::dat_vai_tro( 'Nhân viên', 'Thuỳ Dương', 'TUTU_BD', '', '' );
t( '⚠️ chạm một cơ sở chung là đủ', VHCP_Auth::trong_tam( 'Hai Gian', '' ), '' );

/* ⚠️ VAI KHÁC KHÔNG ĐỔI GÌ: Admin · Kế toán · Giám đốc vẫn thoát sớm, Quản lý vẫn theo cơ sở. */
/* ⚠️ Đặt VAI GỐC thẳng qua phản chiếu: `dat_vai_tro()` quy vai qua `VHCP_Cfg::vai_goc()`, mà
   sổ vai trong bộ nhớ tạm ở đây đã bị thay bằng sổ người dùng giả — quy ra 'Nhân viên' rồi
   phép kiểm đỏ vì cái stub, không phải vì mã thật. */
$p_vai = $ref->getProperty( 'vai_tro' );
$p_vai->setAccessible( true );
$p_vai->setValue( null, 'Kế toán' );
t( '⚠️ kế toán vẫn trông thấy tất, không bị vế mới bó lại',
	VHCP_Auth::trong_tam( 'Ai Đó', 'GIAN BẤT KỲ' ), '' );

/* ═══════════════════════════════════════════════════════════════════════════════════ */
if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo "  · $x\n"; }
	exit( 1 );
}
echo "\n✓ ĐẠT: $DAT phép thử — ô Cơ sở khai bằng mã đơn vị vẫn ra đúng cơ sở, và chỉ cơ sở ấy.\n";
