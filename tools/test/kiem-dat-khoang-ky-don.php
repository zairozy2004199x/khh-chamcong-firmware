<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * ĐƠN CƠ SỞ CỦA KỸ THUẬT — QUYẾT TOÁN THEO KỲ, NHƯNG KỲ DO NGƯỜI LẬP ĐẶT.
 *
 * Anh Thắng, về đơn cơ sở của bộ phận Kỹ thuật: *"quyết toán theo tuần (nhưng không ép buộc
 * tuần nào, khi nào gửi quyết toán thì mới chốt)"*.
 *
 * =============================================================================================
 * Đợt setup rồi tháo dỡ dài ngắn tuỳ nơi, chẳng nằm gọn trong tuần lịch nào. Ép chọn một tuần
 * ngay lúc lập đơn là bắt đoán trước đợt việc kéo tới đâu — đoán sai thì đơn nằm sai tuần, mà
 * tuần là cái ngăn kế toán chốt số.
 *
 * 🔴 CHUỖI KỲ DO MÁY CHỦ DỰNG. Khuôn `T9/2026 (7/9-13/9/2026)` là hợp đồng của cả app: mọi chỗ
 *    đọc kỳ (lọc tuần, xuất MISA, báo cáo) bám vào nó. Nhận sẵn một chuỗi từ trình duyệt là mở
 *    cửa cho khuôn thứ hai lọt vào sổ, rồi lọc theo tuần không bao giờ thấy đơn ấy nữa — mất
 *    đơn trong im lặng, mà cả bảng vẫn trông bình thường.
 *
 * 🔴 GỬI QUYẾT TOÁN RỒI THÌ KHOÁ. Số đã vào sổ, mà kỳ là cái ngăn nó nằm; kéo sang khoảng khác
 *    là báo cáo của CẢ HAI kỳ cùng sai, và kỳ đã chốt thì không ai mở lại để đối chiếu nữa.
 *
 * 🔴 DỰNG XONG PHẢI ĐỌC NGƯỢC RA ĐÚNG KHOẢNG ĐÃ CHO. `ky_tu_khoang()` và `khoang_ky()` là hai
 *    chiều của cùng một khuôn — lệch nhau là đơn nằm ở một kỳ mà mọi phép lọc hiểu thành kỳ
 *    khác.
 *
 * ⚠️ CHẠY THẬT trên dữ liệu thật, đổi vai qua từng bước.
 *
 * Chạy: php tools/test/kiem-dat-khoang-ky-don.php
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
function vai( $v, $ten = 'Ai đó' ) { VHCP_Auth::dat_vai_tro( $v, $ten ); }

/* ═══ 1. DỰNG CHUỖI KỲ TỪ KHOẢNG NGÀY ════════════════════════════════════════════════════ */
teq( 'tuần trọn trong một tháng', 'T9/2026 (7/9-13/9/2026)',
	VHCP_Don::ky_tu_khoang( '2026-09-07', '2026-09-13' ) );
/* 🔴 Kỳ VẮT QUA THÁNG lấy tháng của ngày CUỐI — anh Thắng 01/09/2026: *"giờ luật tạo cho đơn
   mới theo tuần liên tục, không tạo theo tháng nữa"*. Lấy tháng đầu là cùng một tuần sinh ra
   hai tên khác nhau tuỳ chỗ dựng, và lọc theo tuần không gom lại được nữa. */
teq( '🔴 kỳ vắt qua tháng lấy tháng của ngày CUỐI', 'T9/2026 (31/8-6/9/2026)',
	VHCP_Don::ky_tu_khoang( '2026-08-31', '2026-09-06' ) );
teq( '🔴 kỳ vắt qua NĂM lấy năm của ngày cuối', 'T1/2027 (28/12-3/1/2027)',
	VHCP_Don::ky_tu_khoang( '2026-12-28', '2027-01-03' ) );
teq( 'đợt dài hơn một tuần cũng dựng được', 'T9/2026 (1/9-25/9/2026)',
	VHCP_Don::ky_tu_khoang( '2026-09-01', '2026-09-25' ) );
teq( 'đợt đúng một ngày', 'T9/2026 (5/9-5/9/2026)',
	VHCP_Don::ky_tu_khoang( '2026-09-05', '2026-09-05' ) );
teq( '🔴 ngày cuối TRƯỚC ngày đầu → không dựng (kỳ ngược thì mọi báo cáo xếp nhầm chỗ)',
	'', VHCP_Don::ky_tu_khoang( '2026-09-13', '2026-09-07' ) );
teq( 'ngày rỗng → không dựng', '', VHCP_Don::ky_tu_khoang( '', '2026-09-13' ) );
teq( 'ngày bịa (31/02) → không dựng', '', VHCP_Don::ky_tu_khoang( '2026-02-31', '2026-03-02' ) );
teq( 'chuỗi rác → không dựng', '', VHCP_Don::ky_tu_khoang( 'hôm qua', 'hôm nay' ) );

/* ═══ 2. 🔴 DỰNG XONG ĐỌC NGƯỢC RA ĐÚNG KHOẢNG ĐÃ CHO ═══════════════════════════════════ */
foreach ( array(
	array( '2026-09-07', '2026-09-13' ),
	array( '2026-08-31', '2026-09-06' ),
	array( '2026-12-28', '2027-01-03' ),
	array( '2026-09-01', '2026-09-25' ),
	array( '2026-09-05', '2026-09-05' ),
	array( '2027-02-22', '2027-02-28' ),
) as $c ) {
	$ky = VHCP_Don::ky_tu_khoang( $c[0], $c[1] );
	list( $a, $z ) = VHCP_Don::khoang_ky( $ky );
	t( '🔴 ' . $c[0] . '→' . $c[1] . ' dựng "' . $ky . '" rồi đọc ngược ra đúng khoảng ấy',
		$a === $c[0] && $z === $c[1], array( $ky, $a, $z ) );
}

/* ═══ 3. ĐẶT LẠI KHOẢNG NGÀY CỦA MỘT ĐƠN THẬT ═══════════════════════════════════════════ */
vai( 'Nhân viên', 'NV Kỹ thuật' );
$r = VHCP_Don::tao_don_moi( 'T9/2026 (7/9-13/9/2026)', 'NV Kỹ thuật' );
t( 'dựng được đơn thử', ! empty( $r['success'] ), $r );
$ma = $r['maDon'];
VHCP_Don::add_line( $ma, array( 'coso' => 'VR SORA', 'ngay' => '2026-09-08',
	'phanLoaiTT' => 'Thanh toán cá nhân', 'nhom' => 'Chi phí cơ sở',
	'noiDung' => 'Vật tư setup', 'thanhTien' => 500000 ) );

$x = VHCP_Don::dat_khoang_ky( $ma, '2026-09-07', '2026-09-20', 'Đợt setup kéo thêm một tuần' );
t( '🔴 người lập đơn nắn được ngày CUỐI ra xa (đợt việc kéo dài hơn dự tính)',
	! empty( $x['success'] ), $x );
teq( '   kỳ mới đúng khuôn', 'T9/2026 (7/9-20/9/2026)', $x['kyMoi'] );
$d = VHCP_Don::get_don( $ma );
teq( '   và đã ghi vào đơn', 'T9/2026 (7/9-20/9/2026)', trim( (string) $d['don']['ky'] ) );

/* Nhật ký đơn phải nói ai đổi, từ đâu sang đâu, vì sao — tháng sau soi lại còn biết vì sao
   một khoản của tuần này lại nằm ở kỳ kia. */
$su = VHCP_Log::get_log( array( 'target' => $ma ) );
$co = false; $ds = isset( $su['items'] ) ? $su['items'] : ( is_array( $su ) ? $su : array() );
foreach ( (array) $ds as $it ) {
	$m = implode( ' ', array_map( 'strval', (array) $it ) );
	if ( false !== mb_strpos( $m, 'khoảng ngày' ) && false !== mb_strpos( $m, 'kéo thêm' ) ) { $co = true; }
}
t( '🔴 vào NHẬT KÝ ĐƠN kèm lý do (không thì tháng sau không ai biết vì sao kỳ đổi)', $co, $ds );

$x = VHCP_Don::dat_khoang_ky( $ma, '2026-09-07', '2026-09-20' );
t( 'đặt lại đúng khoảng đang có → chối, khỏi ghi một dòng nhật ký vô nghĩa',
	empty( $x['success'] ), $x );
$x = VHCP_Don::dat_khoang_ky( $ma, '2026-09-25', '2026-09-20' );
t( '🔴 ngày cuối trước ngày đầu → CHỐI ở máy chủ (không tin chốt của màn)',
	empty( $x['success'] ), $x );
/* Câu chối phải NÓI ĐƯỢC PHẢI LÀM GÌ. Ngã về câu chung "khoảng ngày không dựng ra được kỳ" thì
   người dùng nhìn hai ô ngày hợp lệ và không hiểu mình sai chỗ nào. */
t( '   và câu chối chỉ đúng chỗ sai',
	isset( $x['error'] ) && false !== mb_strpos( $x['error'], 'sau ngày bắt đầu' ), $x );
teq( '   và đơn giữ nguyên kỳ cũ', 'T9/2026 (7/9-20/9/2026)',
	trim( (string) VHCP_Don::get_don( $ma )['don']['ky'] ) );
$x = VHCP_Don::dat_khoang_ky( $ma, '', '2026-09-20' );
t( 'thiếu ngày → chối', empty( $x['success'] ), $x );
$x = VHCP_Don::dat_khoang_ky( 'KHONG-CO', '2026-09-07', '2026-09-20' );
t( 'đơn không có thật → chối, không nổ', empty( $x['success'] ), $x );

/* 🔴 KHÔNG ĐỔI ĐƯỢC KỲ ĐƠN CỦA NGƯỜI KHÁC. Kỳ là cái ngăn kế toán chốt số; kéo đơn của đồng
   nghiệp sang kỳ khác là đổi con số của hai kỳ mà chủ đơn không hề biết. */
vai( 'Nhân viên', 'NV Khác' );
$x = VHCP_Don::dat_khoang_ky( $ma, '2026-09-07', '2026-09-14' );
t( '🔴 nhân viên khác KHÔNG đổi được kỳ đơn không phải của mình', empty( $x['success'] ), $x );
vai( 'Nhân viên', 'NV Kỹ thuật' );   // đọc lại kỳ bằng mắt CHỦ ĐƠN — người kia còn chẳng mở được đơn
teq( '   và kỳ giữ nguyên', 'T9/2026 (7/9-20/9/2026)',
	trim( (string) VHCP_Don::get_don( $ma )['don']['ky'] ) );

/* ═══ 4. 🔴 GỬI QUYẾT TOÁN RỒI THÌ KHOÁ ════════════════════════════════════════════════ */
vai( 'Admin', 'KT' );
/* Đi ĐÚNG LUỒNG THẬT tới lúc chốt, không thọc tay đổi cột trạng thái: chốt của
   `vi_sao_khong_sua()` đọc trạng thái, mà trạng thái ấy phải do luồng thật đặt ra. */
VHCP_Don::gui_duyet_tam_ung( $ma );
VHCP_Don::duyet_tam_ung( $ma, 'QL' );
VHCP_Don::cap_tam_ung( $ma, 'KT' );
VHCP_Don::gui_quyet_toan( $ma );
teq( '   đơn đã ở "Chờ quyết toán"', 'Chờ quyết toán',
	trim( (string) VHCP_Don::get_don( $ma )['don']['trangThai'] ) );
$x = VHCP_Don::dat_khoang_ky( $ma, '2026-09-07', '2026-09-28' );
t( '🔴 "Chờ quyết toán" thì VẪN đổi được — anh Thắng: *"khi nào gửi quyết toán thì mới chốt"*, '
	. 'mà chốt là lúc kế toán xác nhận, không phải lúc bấm gửi',
	! empty( $x['success'] ), $x );
VHCP_Don::xac_nhan_quyet_toan_cn( $ma, 'KT', 'Hoàn lại', 0 );
teq( '   đơn đã quyết toán', 'Đã quyết toán',
	trim( (string) VHCP_Don::get_don( $ma )['don']['trangThai'] ) );
$x = VHCP_Don::dat_khoang_ky( $ma, '2026-09-07', '2026-09-30' );
t( '🔴 đơn ĐÃ QUYẾT TOÁN → không đổi khoảng ngày được nữa, kể cả Admin',
	empty( $x['success'] ), $x );
teq( '   và kỳ giữ nguyên', 'T9/2026 (7/9-28/9/2026)',
	trim( (string) VHCP_Don::get_don( $ma )['don']['ky'] ) );

/* ═══ 5. CỬA API ═══════════════════════════════════════════════════════════════════════ */
$src = file_get_contents( $goc . '/wordpress/vhcp-chi-phi/includes/class-vhcp-api.php' );
t( '🔴 hàm đã khai vào cửa API (không khai thì màn gọi ra lỗi "hàm lạ")',
	false !== strpos( $src, "'datKhoangKyDon'" )
	&& false !== strpos( $src, "array( 'VHCP_Don', 'dat_khoang_ky' )" ), null );

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo '  · ' . $x . "\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: kỳ do người lập đặt, và gửi quyết toán rồi thì khoá.\n";
