<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * CƠ CẤU CHỨC DANH — PHÒNG BAN DỰNG SẴN, KHÔNG AI ĐẺ VAI LẠ.
 *
 * Anh Thắng 13/09/2026 vạch thang quyền:
 *   · Nhân viên (cơ sở · kỹ thuật) — chỉ xem được cơ sở mình quản lý trở xuống
 *   · Quản lý                      — xem được bộ phận mình trở xuống
 *   · Kế toán bộ phận              — xem được bộ phận mình quản lý
 *   · Giám đốc                     — toàn quyền xem
 *   · Admin                        — toàn quyền
 * rồi chốt cách khai: *"anh sẽ tạo ban bệ phòng ban sẵn, ai thuộc bộ phận nào thì thêm vào,
 * tránh sai vai hay tự tạo vai lạ"*.
 *
 * =============================================================================================
 * 🔴 CÙNG MỘT THÔNG TIN TỪNG KHAI BA NƠI. Vai "Nhân viên kỹ thuật" mang chữ "kỹ thuật" trong
 *    TÊN VAI, khai lần nữa ở cột "Chỉ làm bộ phận" của bảng Vai trò, rồi lần thứ ba ở cột
 *    "Bộ phận" của tài khoản. Ba nơi thì sớm muộn lệch — và lệch ở đây nghĩa là người ta thấy
 *    (hoặc không thấy) chi phí của mảng khác mà chẳng ai giải thích nổi. Nay chỉ còn MỘT nơi.
 *
 * 🔴 ADMIN VÀ GIÁM ĐỐC KHÔNG BỊ BÓ, dù ô Bộ phận của họ khai gì. Không chốt chỗ ấy thì một ô
 *    khai nhầm trên tài khoản giám đốc cắt mất tầm nhìn toàn cục — đúng thứ vai ấy sinh ra để
 *    có. Phần 2 canh riêng điều đó.
 *
 * Chạy: php tools/test/kiem-co-cau-chuc-danh.php
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
/** Đăng nhập như cổng API: vai + cơ sở + PHÒNG BAN, cả ba từ tài khoản. */
function lam( $vai, $ten, $coso = '', $bp = '' ) { VHCP_Auth::dat_vai_tro( $vai, $ten, $coso, $bp ); }

/* ═══ 1. THANG CHỨC DANH ═══════════════════════════════════════════════════════════ */
teq( '🔴 có đủ năm chức danh, Giám đốc đứng trên Quản lý',
	array( 'Giám đốc', 'Quản lý', 'Kế toán cá nhân', 'Kế toán NCC', 'Nhân viên' ),
	VHCP_Cfg::VAI_GOC );
/* Admin không nằm trong danh sách — nó là vai quản trị, đứng ngoài thang. */
t( 'Admin đứng ngoài thang (không nằm trong VAI_GOC)',
	! in_array( 'Admin', VHCP_Cfg::VAI_GOC, true ), VHCP_Cfg::VAI_GOC );
teq( '🔴 "Giám đốc" là vai gốc, không bị quy về Nhân viên', 'Giám đốc', VHCP_Cfg::vai_goc( 'Giám đốc' ) );

/* ═══ 2. PHÒNG BAN BÓ AI, THA AI ═══════════════════════════════════════════════════ */
VHCP_Cfg::seed();
VHCP_Cfg::write( VHCP_Cfg::LOAI, array(
	array( 'Sửa máy gắp thú', '6417', '', '', 'Máy tự động', '', '', '' ),
	array( 'Chạy quảng cáo',  '6418', '', '', 'Marketing',   '', '', '' ),
	array( 'Chi phí khác',    '6428', '', '', '',            '', '', '' ),   // chưa khai bộ phận
) );
VHCP_Cfg::clear_cache();

lam( 'Quản lý', 'Hòa', '', 'Máy tự động' );
teq( '🔴 Quản lý bị bó vào phòng ban của TÀI KHOẢN', 'Máy tự động', VHCP_Auth::bo_phan_bo() );
teq( '   nên thấy loại của phòng mình',  true,  VHCP_Auth::xem_duoc_loai( 'Sửa máy gắp thú' ) );
teq( '🔴 và KHÔNG thấy loại của phòng khác', false, VHCP_Auth::xem_duoc_loai( 'Chạy quảng cáo' ) );
/* ⚠️ Loại CHƯA khai bộ phận vẫn cho qua — danh mục dựng từ sổ cũ còn rất nhiều dòng bỏ trống,
   chặn chúng là ngày bản này lên người ta mở màn ra thấy gần như trắng. */
teq( '⚠️ loại chưa khai bộ phận thì ai cũng thấy', true, VHCP_Auth::xem_duoc_loai( 'Chi phí khác' ) );

lam( 'Kế toán cá nhân', 'Nhân', '', 'Marketing' );
teq( '🔴 Kế toán cũng bị bó vào phòng ban của mình', 'Marketing', VHCP_Auth::bo_phan_bo() );
teq( '   thấy loại phòng mình',       true,  VHCP_Auth::xem_duoc_loai( 'Chạy quảng cáo' ) );
teq( '🔴 không thấy loại phòng khác', false, VHCP_Auth::xem_duoc_loai( 'Sửa máy gắp thú' ) );

lam( 'Nhân viên', 'Bin', 'FARM PHAN THIẾT', 'Kỹ thuật' );
teq( '🔴 Nhân viên cũng bó theo phòng ban', 'Kỹ thuật', VHCP_Auth::bo_phan_bo() );

/* 🔴 HAI VAI TOÀN QUYỀN KHÔNG BỊ BÓ, DÙ Ô BỘ PHẬN KHAI GÌ. Đây là phép quan trọng nhất của
   phần này: để nó hỏng thì một ô khai nhầm cắt mất tầm nhìn của chính người cần nhìn cả hệ. */
lam( 'Giám đốc', 'Sếp Hai', '', 'Marketing' );
teq( '🔴 GIÁM ĐỐC không bị bó, dù ô Bộ phận có khai', '', VHCP_Auth::bo_phan_bo() );
teq( '   nên thấy MỌI loại · máy tự động', true, VHCP_Auth::xem_duoc_loai( 'Sửa máy gắp thú' ) );
teq( '   và · marketing',                  true, VHCP_Auth::xem_duoc_loai( 'Chạy quảng cáo' ) );
lam( 'Admin', 'Sếp', '', 'Kỹ thuật' );
teq( '🔴 ADMIN cũng vậy', '', VHCP_Auth::bo_phan_bo() );
teq( '   thấy mọi loại', true, VHCP_Auth::xem_duoc_loai( 'Chạy quảng cáo' ) );

/* Ô để trống = không bó, giữ nguyên hành vi cũ của phần lớn tài khoản. */
lam( 'Quản lý', 'Toàn', '', '' );
teq( '⚠️ ô Bộ phận để trống = KHÔNG bó', '', VHCP_Auth::bo_phan_bo() );
teq( '   nên thấy mọi loại', true, VHCP_Auth::xem_duoc_loai( 'Sửa máy gắp thú' ) );

/* ═══ 3. PHẠM VI CƠ SỞ — GIÁM ĐỐC THOÁT CÙNG CHỖ VỚI ADMIN ════════════════════════ */
lam( 'Nhân viên', 'Bin', 'FARM PHAN THIẾT', '' );
t( '🔴 Nhân viên: thấy cơ sở mình',        VHCP_Auth::trong_tam( 'Ai đó', 'FARM PHAN THIẾT' ) );
t( '🔴 Nhân viên: KHÔNG thấy cơ sở khác', ! VHCP_Auth::trong_tam( 'Ai đó', 'TÀU TẤN PHÚ' ) );
t( '   nhưng vẫn thấy đơn DO MÌNH lập',    VHCP_Auth::trong_tam( 'Bin', 'TÀU TẤN PHÚ' ) );

lam( 'Quản lý', 'Hòa', 'FARM PHAN THIẾT', '' );
t( '🔴 Quản lý CÓ khai cơ sở thì bó theo cơ sở ấy', ! VHCP_Auth::trong_tam( 'Ai đó', 'TÀU TẤN PHÚ' ) );
lam( 'Quản lý', 'Toàn', '', '' );
t( '⚠️ Quản lý KHÔNG khai cơ sở thì trông cả nhà',   VHCP_Auth::trong_tam( 'Ai đó', 'TÀU TẤN PHÚ' ) );

/* 🔴 Giám đốc phải thoát khỏi chốt cơ sở y như Admin. Vai này mới nên chưa lọt vào chốt nào —
   để nó rơi xuống nhánh dưới thì một ô Cơ sở khai nhầm là cắt mất tầm nhìn toàn cục. */
lam( 'Giám đốc', 'Sếp Hai', 'FARM PHAN THIẾT', '' );
t( '🔴 GIÁM ĐỐC thấy mọi cơ sở, dù ô Cơ sở có khai', VHCP_Auth::trong_tam( 'Ai đó', 'TÀU TẤN PHÚ' ) );
lam( 'Admin', 'Sếp', 'FARM PHAN THIẾT', '' );
t( '   Admin cũng vậy', VHCP_Auth::trong_tam( 'Ai đó', 'TÀU TẤN PHÚ' ) );
lam( 'Kế toán cá nhân', 'Nhân', 'FARM PHAN THIẾT', '' );
t( '   Kế toán không bó theo cơ sở (bó theo phòng ban)', VHCP_Auth::trong_tam( 'Ai đó', 'TÀU TẤN PHÚ' ) );

/* ═══ 4. 🔴 CỔNG API PHẢI TRUYỀN PHÒNG BAN XUỐNG ══════════════════════════════════
 * Mọi phép trên đây gọi thẳng `dat_vai_tro()` nên chúng chỉ chứng minh cái HÀM chạy đúng.
 * Ngoài đời người dùng đi qua cổng API — cổng mà quên truyền tham số thứ tư thì `$bo_phan`
 * rỗng ở MỌI lượt gọi, và mọi chốt phòng ban im lặng mở toang trong khi bộ thử vẫn xanh
 * rờn. Phá thử chỉ đúng chỗ ấy ngày 13/09/2026.
 *
 * 🔴 Cùng bài học với vụ hai nút sắp xếp bị cái khoá nuốt mất (12/09): canh cái hàm chạy
 *    đúng là CHƯA ĐỦ khi người dùng không với tới được nó. Phép dưới soi ĐƯỜNG ĐI.
 * ═════════════════════════════════════════════════════════════════════════════════════ */
$api = file_get_contents( $goc . '/wordpress/vhcp-chi-phi/includes/class-vhcp-api.php' );
/* Gỡ chú thích trước khi quét — khối chú thích ngay trên lời gọi có nhắc chữ "boPhan", nên
   quét cả chú thích là phép xanh kể cả khi tham số đã bị gỡ khỏi lời gọi. */
$api_ma = preg_replace( '#/\*[\s\S]*?\*/#', ' ', $api );
$api_ma = preg_replace( '#//[^\n]*#', ' ', $api_ma );
if ( preg_match( '/VHCP_Auth::dat_vai_tro\(\s*\$role_ht[^;]*;/', $api_ma, $m ) ) {
	t( '🔴 cổng API truyền PHÒNG BAN của tài khoản xuống phiên',
		false !== strpos( $m[0], "boPhan" ), $m[0] );
	t( '   và vẫn truyền cơ sở như cũ', false !== strpos( $m[0], "'coso'" ), $m[0] );
} else {
	t( 'tìm được lời gọi dat_vai_tro ở cổng API', false, 'không khớp khuôn' );
}

/* ─────────────────────────────────────────────────────────────────────────────────── */
echo "\n";
if ( $TRUOT ) {
	echo '❌ TRƯỢT ' . count( $TRUOT ) . ' / ' . ( $DAT + count( $TRUOT ) ) . "\n";
	foreach ( $TRUOT as $x ) { echo "   • $x\n"; }
	exit( 1 );
}
echo "✅ ĐẠT $DAT / $DAT — năm chức danh, phòng ban đi theo tài khoản, Giám đốc nhìn cả hệ\n";
