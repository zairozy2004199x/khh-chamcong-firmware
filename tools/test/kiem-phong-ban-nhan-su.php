<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * PHÒNG BAN — SỔ NHÂN SỰ QUYẾT ĐỊNH, TRANG CHI PHÍ CHỈ ĐỌC.
 *
 * Anh Thắng 13/09/2026: *"quyết định bộ phận do nhân sự quyết định, bên chi phí chỉ biết bộ phận
 * đó có được quyền không thôi, chứ không can thiệp được đổi bộ phận của nhân viên truyền sang"*.
 *
 * =============================================================================================
 * 🔴 LỖI BẢN NÀY SINH RA ĐỂ DẸP: CẦU ĐẨY NHÉT `chuc_vu` VÀO Ô `bo_phan`.
 *
 *    `VHCC_DayChiPhi::ho_so_day()` lấy thẳng cột `chuc_vu` làm phòng ban. Nhưng chức vụ là
 *    *Thu ngân · Ca trưởng · Giám sát · Bảo vệ · Pha chế* — việc người ta LÀM. Còn ô Bộ phận bên
 *    chi phí nhận đúng bảy tên *Cơ sở · Văn phòng · Kỹ thuật · Marketing · Công tác · Setup ·
 *    Máy tự động*, và nó quyết định người ấy thấy MẢNG CHI PHÍ nào.
 *
 *    Hai bộ chỉ trùng một tên ("Kỹ thuật"). Nên đẩy một Thu ngân sang là ô Bộ phận của họ thành
 *    "Thu ngân", chốt phòng ban đi tìm loại chi phí thuộc "Thu ngân", không thấy cái nào, và màn
 *    chi phí của người ấy trắng — không một câu lỗi. Phần 3 canh đúng chỗ đó.
 *
 * 🔴 VÀ MỘT LỖ CŨ HƠN: tab Admin sửa hồ sơ xong KHÔNG gọi `dong_bo()`. Trang Nhân sự riêng có
 *    gọi (ba chỗ), đường "Quên PIN" có gọi — riêng tab Admin thì không, nên sửa ở đấy là bản sao
 *    bên chi phí đứng im. Phần 4 canh đường đi ấy.
 *
 * Chạy: php tools/test/kiem-phong-ban-nhan-su.php
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

/* ═══ 1. DANH MỤC: MỘT BỘ DUY NHẤT, VÀ NÓ Ở BÊN CHI PHÍ ═══════════════════════════
 * Anh Thắng chốt 13/09/2026: nhân sự khai bằng đúng bảy tên trang chi phí đang dùng. Giữ bản
 * sao bên này là anh thêm một phòng ban bên kia mà ô bên này vẫn xổ danh sách cũ.
 * ═════════════════════════════════════════════════════════════════════════════════════ */
VHCP_Cfg::seed();
teq( '🔴 danh mục phòng ban lấy THẲNG từ trang Chi phí',
	VHCP_Cfg::bo_phan_ds(), VHCC_NhanSu::phong_ban_ds() );
t( '   và nó đúng là bảy tên của bên ấy, không phải bốn tên tính lương',
	VHCC_NhanSu::phong_ban_ds() !== VHCC_Luong::BP_DS, VHCC_NhanSu::phong_ban_ds() );

/* Khai thêm một phòng ban bên Chi phí -> ô bên Nhân sự thấy ngay, khỏi sửa mã. */
VHCP_Cfg::write( VHCP_Cfg::BP, array( array( 'Cơ sở' ), array( 'Kỹ thuật' ), array( 'Kho vận' ) ) );
VHCP_Cfg::clear_cache();
t( '🔴 khai thêm phòng ban bên Chi phí thì ô bên Nhân sự có ngay',
	in_array( 'Kho vận', VHCC_NhanSu::phong_ban_ds(), true ), VHCC_NhanSu::phong_ban_ds() );
teq( '   và chuẩn hoá được tên vừa khai', 'Kho vận', VHCC_NhanSu::phong_ban_chuan( 'kho VẬN' ) );

/* 🔴 TÊN LẠ TRẢ VỀ RỖNG. Đây là ô quyết định người ta thấy mảng nào; nhận bừa "Ky thuat" thiếu
   dấu là bên chi phí đi tìm phòng ban không có thật và màn của họ trắng. */
teq( '🔴 tên ngoài danh mục -> rỗng', '', VHCC_NhanSu::phong_ban_chuan( 'Ky thuat' ) );
teq( '🔴 chức vụ KHÔNG phải phòng ban', '', VHCC_NhanSu::phong_ban_chuan( 'Thu ngân' ) );
teq( '⚠️ ô trống vẫn hợp lệ (= chưa xếp)', '', VHCC_NhanSu::phong_ban_chuan( '' ) );
/* Trả danh mục về mặc định cho các phần sau. `seed()` chỉ gieo khi bảng TRỐNG, nên phải xoá
   trắng rồi mới gọi — ghi đè bảng ở phép trên mà không dọn là mọi phần dưới chạy trên một danh
   mục ba tên và trượt hàng loạt vì đúng cái cảnh mình vừa dựng. */
VHCP_Cfg::write( VHCP_Cfg::BP, array() );
VHCP_Cfg::clear_cache();
teq( '   (đã trả danh mục về bảy tên mặc định)',
	VHCP_Cfg::BO_PHAN_DS, VHCC_NhanSu::phong_ban_ds() );

/* ═══ 2. GHI HỒ SƠ: CHỐI TÊN LẠ, VÀ CHỈ ADMIN / QUẢN LÝ XẾP ĐƯỢC ══════════════════ */
$ad  = array( 'name' => 'Sếp',  'ma_nv' => 'AD1', 'role' => 'Admin' );
$cht = array( 'name' => 'Trưởng', 'ma_nv' => 'CH1', 'role' => 'Cửa hàng trưởng', 'coso' => 'FARM PHAN THIẾT' );

$r = VHCC_NhanSu::luu_ho_so( $ad, array( 'ma_nv' => 'P001', 'ho_ten' => 'Anh Một',
	'cua_hang' => 'FARM PHAN THIẾT', 'chuc_vu' => 'Thu ngân', 'phong_ban' => 'Kỹ thuật' ) );
t( 'Admin xếp được phòng ban', ! empty( $r['ok'] ), $r );
$hs = VHCC_NhanSu::ho_so( 'P001' );
teq( '🔴 ghi đúng vào cột phong_ban', 'Kỹ thuật', (string) $hs['phong_ban'] );
teq( '⚠️ và KHÔNG đụng tới cột chuc_vu', 'Thu ngân', (string) $hs['chuc_vu'] );

$r = VHCC_NhanSu::luu_ho_so( $ad, array( 'ma_nv' => 'P001', 'phong_ban' => 'Thu ngân' ) );
t( '🔴 xếp phòng ban bằng một CHỨC VỤ thì bị chối', empty( $r['ok'] ), $r );
t( '   và câu chối bày ra danh mục đúng',
	isset( $r['error'] ) && false !== mb_strpos( $r['error'], 'Marketing' ), $r );
teq( '   hồ sơ giữ nguyên giá trị cũ', 'Kỹ thuật', (string) VHCC_NhanSu::ho_so( 'P001' )['phong_ban'] );

$r = VHCC_NhanSu::luu_ho_so( $ad, array( 'ma_nv' => 'P001', 'phong_ban' => '  marketing ' ) );
t( 'chuẩn hoá hoa thường + khoảng trắng khi ghi', ! empty( $r['ok'] ), $r );
teq( '   ghi xuống đúng cách viết trong danh mục', 'Marketing', (string) VHCC_NhanSu::ho_so( 'P001' )['phong_ban'] );

/* ══════════════════════════════════════════════════════════════════════════════════════
 * 🔴 AI XẾP ĐƯỢC PHÒNG BAN — VÀ VÌ SAO KHÔNG CÓ CHỐT RIÊNG CHO Ô NÀY.
 *
 * Bản đầu gác thêm `co_quan_tri_nv()` cho riêng ô phòng ban. Phá thử 13/09/2026 bỏ hẳn dòng
 * gác ấy mà bộ thử vẫn xanh — vì nó KHÔNG CHẶN ĐƯỢC AI: `luu_ho_so()` đã đòi quyền `ho_so`
 * (bậc Kế toán, 4) ngay ở chốt đầu, còn `co_quan_tri_nv()` là `ngoai_coso` (bậc Quản lý, 3),
 * THẤP HƠN. Ai qua chốt đầu thì đương nhiên qua chốt sau.
 *
 * Chốt thật nằm ở chính `co_sua_ho_so()`, và nó đủ mạnh. Phép dưới canh đúng cái chốt ấy —
 * chứ không canh một hàng rào đặt lệch chỗ.
 * ══════════════════════════════════════════════════════════════════════════════════════ */
$r = VHCC_NhanSu::luu_ho_so( $cht, array( 'ma_nv' => 'P001', 'sdt' => '0900000001',
	'phong_ban' => 'Setup' ) );
t( '🔴 Cửa hàng trưởng KHÔNG sửa được hồ sơ, nên không xếp được phòng ban', empty( $r['ok'] ), $r );
teq( '   hồ sơ giữ nguyên phòng ban cũ', 'Marketing',
	(string) VHCC_NhanSu::ho_so( 'P001' )['phong_ban'] );
teq( '   và giữ nguyên cả ô khác trong cùng lượt', '',
	(string) VHCC_NhanSu::ho_so( 'P001' )['sdt'] );

/* Kế toán thì xếp được — bậc ấy đã cấp được PIN và đặt được vai trò đăng nhập, hai thứ nguy
   hơn hẳn một ô phòng ban, nên dựng thêm bậc riêng cho ô này là thừa. */
$kt = array( 'name' => 'Kế toán', 'ma_nv' => 'KT1', 'role' => 'Kế toán cá nhân' );
$r  = VHCC_NhanSu::luu_ho_so( $kt, array( 'ma_nv' => 'P001', 'phong_ban' => 'Setup' ) );
t( '✅ Kế toán xếp được phòng ban', ! empty( $r['ok'] ), $r );
teq( '   ghi xuống đúng', 'Setup', (string) VHCC_NhanSu::ho_so( 'P001' )['phong_ban'] );
/* Trả về Marketing cho phần sau khỏi lệ thuộc thứ tự. */
VHCC_NhanSu::luu_ho_so( $ad, array( 'ma_nv' => 'P001', 'phong_ban' => 'Marketing' ) );

/* ═══ 3. CẦU ĐẨY: PHÒNG BAN, KHÔNG PHẢI CHỨC VỤ ══════════════════════════════════ */
VHCC_NhanSu::luu_ho_so( $ad, array( 'ma_nv' => 'P002', 'ho_ten' => 'Anh Hai',
	'cua_hang' => 'FARM PHAN THIẾT', 'chuc_vu' => 'Ca trưởng', 'phong_ban' => 'Cơ sở',
	'pin_dang_nhap' => '7788', 'vai_tro' => 'Nhân viên' ) );

$hsd = VHCC_DayChiPhi::ho_so_day( 'P002' );
t( 'dựng được hồ sơ để đẩy', is_array( $hsd ), $hsd );
teq( '🔴 ô bo_phan lấy từ PHÒNG BAN', 'Cơ sở', $hsd['bo_phan'] );
t( '🔴 và KHÔNG phải chức vụ ("Ca trưởng")', 'Ca trưởng' !== $hsd['bo_phan'], $hsd['bo_phan'] );
teq( '🔴 mang theo cả Mã NV — sợi dây nối hai bên', 'P002', $hsd['ma_nv'] );

/* Chưa xếp phòng ban thì ĐỂ RỖNG, đừng đoán. Rỗng bên chi phí = không bó, rộng hơn ý muốn
   nhưng không làm ai mất việc; đoán bừa thì cắt mất đúng mảng họ cần. */
VHCC_NhanSu::luu_ho_so( $ad, array( 'ma_nv' => 'P003', 'ho_ten' => 'Anh Ba',
	'cua_hang' => 'FARM PHAN THIẾT', 'chuc_vu' => 'Bảo vệ', 'pin_dang_nhap' => '7799',
	'vai_tro' => 'Nhân viên' ) );
teq( '⚠️ chưa xếp phòng ban -> đẩy sang ô RỖNG, không mượn chức vụ', '',
	VHCC_DayChiPhi::ho_so_day( 'P003' )['bo_phan'] );

/* Đẩy thật, rồi soi hàng bên chi phí. */
$kq = VHCC_DayChiPhi::dat( $ad, 'P002', 'mo' );
t( 'đẩy sang chi phí được', ! empty( $kq['ok'] ), $kq );
$hang = null;
foreach ( VHCP_Cfg::get_users() as $u ) {
	if ( 'Anh Hai' === trim( (string) $u['ten'] ) ) { $hang = $u; break; }
}
t( 'hàng đã có mặt bên chi phí', is_array( $hang ), $hang );
teq( '🔴 ô Bộ phận bên chi phí = phòng ban', 'Cơ sở', (string) $hang['boPhan'] );
teq( '🔴 ô Mã NV bên chi phí được điền',     'P002', (string) $hang['maNv'] );
teq( '   PIN đi theo',                        '7788', (string) $hang['pin'] );
/* 🔴 GIÁ TRỊ ẤY PHẢI DÙNG ĐƯỢC NGAY — không chỉ "có mặt". Đẩy xong mà chuỗi ấy không khớp
   danh mục nào thì y như cũ: người ấy không thấy loại chi phí nào. */
VHCP_Auth::dat_vai_tro( 'Nhân viên', 'Anh Hai', '', (string) $hang['boPhan'] );
teq( '🔴 và bên chi phí hiểu được nó', 'Cơ sở', VHCP_Auth::bo_phan_bo() );
t( '   tức nó nằm trong danh mục bộ phận',
	in_array( VHCP_Auth::bo_phan_bo(), VHCP_Cfg::bo_phan_ds(), true ), VHCP_Cfg::bo_phan_ds() );

/* Sửa phòng ban bên nhân sự -> `dong_bo()` kéo sang, không phải đẩy lại tay. */
VHCC_NhanSu::luu_ho_so( $ad, array( 'ma_nv' => 'P002', 'phong_ban' => 'Setup' ) );
VHCC_DayChiPhi::dong_bo( 'P002' );
$hang2 = null;
foreach ( VHCP_Cfg::get_users() as $u ) {
	if ( 'Anh Hai' === trim( (string) $u['ten'] ) ) { $hang2 = $u; break; }
}
teq( '🔴 đổi phòng ban bên Nhân sự -> bản sao bên Chi phí theo', 'Setup', (string) $hang2['boPhan'] );

/* ⚠️ NHỮNG CỘT KẾ TOÁN TỰ KHAI PHẢI CÒN NGUYÊN. Sổ nhân sự không biết TK Có · Mã đối tượng ·
   Đơn vị, và không được đoán. Đẩy mà xoá chúng là kế toán mất bảng khai mà không ai báo. */
$rows = VHCP_Cfg::read( VHCP_Cfg::USER );
foreach ( $rows as $i => $r0 ) {
	$r0 = array_values( (array) $r0 );
	if ( 0 === strcasecmp( trim( (string) $r0[0] ), 'Anh Hai' ) ) {
		$r0[5] = 'DT999'; $r0[7] = 'POSH';
		$rows[ $i ] = $r0;
	}
}
VHCP_Cfg::write( VHCP_Cfg::USER, $rows );
VHCP_Cfg::clear_cache();
VHCC_DayChiPhi::dong_bo( 'P002' );
$hang3 = null;
foreach ( VHCP_Cfg::get_users() as $u ) {
	if ( 'Anh Hai' === trim( (string) $u['ten'] ) ) { $hang3 = $u; break; }
}
teq( '⚠️ Mã đối tượng kế toán khai vẫn còn', 'DT999', (string) $hang3['maDt'] );
teq( '⚠️ Đơn vị kế toán khai vẫn còn',       'POSH',  (string) $hang3['donVi'] );
teq( '   và Mã NV không bị mất khi đồng bộ lại', 'P002', (string) $hang3['maNv'] );

/* ═══ 4. 🔴 ĐƯỜNG ĐI — TAB ADMIN PHẢI GỌI `dong_bo()` ════════════════════════════
 * Ba phần trên gọi thẳng vào lớp nên chúng chỉ chứng minh CÁI HÀM chạy đúng. Ngoài đời anh
 * Thắng sửa hồ sơ ở tab Admin — mà chính đường ấy trước giờ quên gọi `dong_bo()`, nên sửa xong
 * thì bản sao bên chi phí đứng im. Cùng bài học với hai nút sắp xếp bị cái khoá nuốt mất
 * (12/09/2026): canh cái hàm chạy đúng là CHƯA ĐỦ khi đường đi tới nó bị đứt.
 * ═════════════════════════════════════════════════════════════════════════════════════ */
$web = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-web.php' );
$web_ma = preg_replace( '#/\*.*?\*/#s', '', $web );
$web_ma = preg_replace( '#//[^\n]*#', '', $web_ma );
teq( '🔴 tab Admin gọi dong_bo() ở CẢ HAI đường lưu (một người + lưới hàng loạt)',
	2, substr_count( $web_ma, 'VHCC_DayChiPhi::dong_bo(' ) );
t( '🔴 ô phong_ban nằm trong danh sách cột sửa được',
	false !== strpos( $web_ma, "'phong_ban'" ), null );
/* Lưới sửa hàng loạt gom mã từ `$ten_o` — sót tên ở đó là ô dựng ra trên màn, người ta sửa,
   màn báo "đã lưu", mà giá trị không đi đâu cả. */
if ( preg_match( '/\$ten_o\s*=\s*array\((.*?)\);/s', $web_ma, $m_to ) ) {
	t( '🔴 và nằm trong $ten_o của lưới sửa hàng loạt',
		false !== strpos( $m_to[1], "'phong_ban'" ), $m_to[1] );
} else {
	t( 'đọc được $ten_o', false, 'không khớp regex' );
}
/* Cầu đẩy không được còn một lời gọi `chuc_vu` nào trong thân `ho_so_day()`. */
$day = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-day-chi-phi.php' );
$day_ma = preg_replace( '#/\*.*?\*/#s', '', $day );
$day_ma = preg_replace( '#//[^\n]*#', '', $day_ma );
t( "🔴 cầu đẩy KHÔNG còn đọc \$hs['chuc_vu'] ở đâu cả",
	false === strpos( $day_ma, "chuc_vu" ), $day_ma );

/* ═══ KẾT ═══════════════════════════════════════════════════════════════════════ */
echo "\n";
if ( $TRUOT ) {
	echo "TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo "  ✗ $x\n"; }
	exit( 1 );
}
echo "ĐẠT: $DAT phép thử\nTất cả phép thử đều đạt.\n";
