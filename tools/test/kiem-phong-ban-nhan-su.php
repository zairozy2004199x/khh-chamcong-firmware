<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * PHÒNG BAN — SỔ NHÂN SỰ QUYẾT ĐỊNH, TRANG CHI PHÍ CHỈ ĐỌC.
 *
 * Anh Thắng 13/09/2026: *"quyết định bộ phận do nhân sự quyết định, bên chi phí chỉ biết bộ phận
 * đó có được quyền không thôi, chứ không can thiệp được đổi bộ phận của nhân viên truyền sang"*.
 *
 * =============================================================================================
 * 🔴 LỖI BÀI NÀY SINH RA ĐỂ DẸP: CẦU ĐẨY NHÉT `chuc_vu` VÀO Ô `bo_phan` BÊN CHI PHÍ.
 *
 *    `VHCC_DayChiPhi::ho_so_day()` lấy thẳng cột `chuc_vu` làm phòng ban. Nhưng chức vụ là
 *    *Thu ngân · Ca trưởng · Giám sát · Bảo vệ · Pha chế* — việc người ta LÀM; còn ô Bộ phận bên
 *    chi phí là thứ quyết định họ thấy MẢNG CHI PHÍ nào. Hai bộ từ vựng không dính dáng gì nhau.
 *
 *    Nên đẩy một Thu ngân sang là ô Bộ phận của họ thành "Thu ngân", chốt phòng ban bên ấy đi
 *    tìm loại chi phí thuộc "Thu ngân", không thấy cái nào, và màn chi phí của người ấy gần như
 *    trắng — không một câu lỗi. Phần 2 canh đúng chỗ đó.
 *
 * 🔴 VÀ MỘT LỖ CŨ HƠN: tab Admin sửa hồ sơ xong KHÔNG gọi `dong_bo()`. Trang Nhân sự riêng có
 *    gọi (ba chỗ), đường "Quên PIN" có gọi — riêng tab Admin thì không, nên sửa ở đấy là bản sao
 *    bên chi phí đứng im. Phần 3 canh đường đi ấy.
 *
 * ⚠️ CỘT PHÒNG BAN LÀ `nhan_vien.bo_phan` — sơ đồ tổ chức, khai ở màn nhân sự
 *    (`VHCC_NhanSu::ds_bo_phan()`). KHÔNG dựng thêm cột nào nữa: bài này từng thêm một cột
 *    `phong_ban` song song, rồi hoà nhánh mới thấy sổ đã có sẵn `bo_phan` làm đúng việc ấy.
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

$ad = array( 'name' => 'Sếp', 'ma_nv' => 'AD1', 'role' => 'Admin' );

/* ═══ 1. SỔ NHÂN SỰ CÓ CHỖ KHAI PHÒNG BAN, TÁCH HẲN CHỨC VỤ ═══════════════════════ */
$cot = array();
foreach ( (array) $GLOBALS['wpdb']->get_col( 'SHOW COLUMNS FROM ' . VHCC_DB::t( 'nhan_vien' ) ) as $c ) {
	$cot[] = (string) $c;
}
t( '🔴 hồ sơ có cột bo_phan (sơ đồ tổ chức)', in_array( 'bo_phan', $cot, true ), $cot );
t( '   và vẫn có chuc_vu riêng — hai thứ khác nhau', in_array( 'chuc_vu', $cot, true ), null );
t( '⚠️ KHÔNG đẻ thêm cột phong_ban song song', ! in_array( 'phong_ban', $cot, true ), $cot );
t( 'danh mục phòng ban khai được, không rỗng', count( VHCC_NhanSu::ds_bo_phan() ) > 0,
	VHCC_NhanSu::ds_bo_phan() );

/* ═══ 2. CẦU ĐẨY: PHÒNG BAN, KHÔNG PHẢI CHỨC VỤ ══════════════════════════════════ */
VHCC_NhanSu::luu_ho_so( $ad, array( 'ma_nv' => 'P002', 'ho_ten' => 'Anh Hai',
	'cua_hang' => 'FARM PHAN THIẾT', 'chuc_vu' => 'Ca trưởng',
	'pin_dang_nhap' => '7788', 'vai_tro' => 'Nhân viên' ) );
VHCC_NhanSu::dat_mang_bo_phan( $ad, 'P002', '', 'Phòng Kỹ Thuật' );

$hsd = VHCC_DayChiPhi::ho_so_day( 'P002' );
t( 'dựng được hồ sơ để đẩy', is_array( $hsd ), $hsd );
teq( '🔴 ô bo_phan lấy từ PHÒNG BAN của hồ sơ', 'Phòng Kỹ Thuật', $hsd['bo_phan'] );
t( '🔴 và KHÔNG phải chức vụ ("Ca trưởng")', 'Ca trưởng' !== $hsd['bo_phan'], $hsd['bo_phan'] );
teq( '🔴 mang theo cả Mã NV — sợi dây nối hai bên', 'P002', $hsd['ma_nv'] );

/* ⚠️ CHƯA XẾP PHÒNG BAN THÌ ĐỂ RỖNG, ĐỪNG MƯỢN CHỨC VỤ. Rỗng bên chi phí = không bó, rộng hơn
   ý muốn nhưng không làm ai mất việc; mượn bừa thì cắt mất đúng mảng họ cần.
   ⚠️ Người CÓ cơ sở thì `mang_bo_phan_cua()` suy ra "Khối Nhân Viên Cơ Sở" — nhưng đó là phép
      SUY để bày lên màn, không phải giá trị KHAI. Cầu đẩy chỉ đẩy cái đã khai. */
VHCC_NhanSu::luu_ho_so( $ad, array( 'ma_nv' => 'P003', 'ho_ten' => 'Anh Ba',
	'cua_hang' => 'FARM PHAN THIẾT', 'chuc_vu' => 'Bảo vệ', 'pin_dang_nhap' => '7799',
	'vai_tro' => 'Nhân viên' ) );
teq( '⚠️ chưa khai phòng ban -> đẩy sang ô RỖNG, không mượn chức vụ', '',
	VHCC_DayChiPhi::ho_so_day( 'P003' )['bo_phan'] );

/* Đẩy thật, rồi soi hàng bên chi phí. */
$kq = VHCC_DayChiPhi::dat( $ad, 'P002', 'mo' );
t( 'đẩy sang chi phí được', ! empty( $kq['ok'] ), $kq );
function hang_cp( $ten ) {
	foreach ( VHCP_Cfg::get_users() as $u ) {
		if ( $ten === trim( (string) $u['ten'] ) ) { return $u; }
	}
	return null;
}
$hang = hang_cp( 'Anh Hai' );
t( 'hàng đã có mặt bên chi phí', is_array( $hang ), $hang );
teq( '🔴 ô Bộ phận bên chi phí = phòng ban', 'Phòng Kỹ Thuật', (string) $hang['boPhan'] );
teq( '🔴 ô Mã NV bên chi phí được điền',     'P002', (string) $hang['maNv'] );
teq( '   PIN đi theo',                        '7788', (string) $hang['pin'] );

/* Sửa phòng ban bên nhân sự -> `dong_bo()` kéo sang, không phải đẩy lại tay. */
VHCC_NhanSu::dat_mang_bo_phan( $ad, 'P002', '', 'Phòng Marketing' );
VHCC_DayChiPhi::dong_bo( 'P002' );
teq( '🔴 đổi phòng ban bên Nhân sự -> bản sao bên Chi phí theo', 'Phòng Marketing',
	(string) hang_cp( 'Anh Hai' )['boPhan'] );

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
$h3 = hang_cp( 'Anh Hai' );
teq( '⚠️ Mã đối tượng kế toán khai vẫn còn', 'DT999', (string) $h3['maDt'] );
teq( '⚠️ Đơn vị kế toán khai vẫn còn',       'POSH',  (string) $h3['donVi'] );
teq( '   và Mã NV không bị mất khi đồng bộ lại', 'P002', (string) $h3['maNv'] );

/* ═══ 3. 🔴 ĐƯỜNG ĐI — TAB ADMIN PHẢI GỌI `dong_bo()` ════════════════════════════
 * Phần trên gọi thẳng vào lớp nên nó chỉ chứng minh CÁI HÀM chạy đúng. Ngoài đời anh Thắng sửa
 * hồ sơ ở tab Admin — mà chính đường ấy trước giờ quên gọi `dong_bo()`, nên sửa xong thì bản
 * sao bên chi phí đứng im. Cùng bài học với hai nút sắp xếp bị cái khoá nuốt mất (12/09/2026):
 * canh cái hàm chạy đúng là CHƯA ĐỦ khi đường đi tới nó bị đứt.
 * ═════════════════════════════════════════════════════════════════════════════════════ */
$web = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-web.php' );
$web_ma = preg_replace( '#/\*.*?\*/#s', '', $web );
$web_ma = preg_replace( '#//[^\n]*#', '', $web_ma );
teq( '🔴 tab Admin gọi dong_bo() ở CẢ HAI đường lưu (một người + lưới hàng loạt)',
	2, substr_count( $web_ma, 'VHCC_DayChiPhi::dong_bo(' ) );

/* Cầu đẩy không được còn một lời gọi `chuc_vu` nào trong mã chạy. */
$day = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-day-chi-phi.php' );
$day_ma = preg_replace( '#/\*.*?\*/#s', '', $day );
$day_ma = preg_replace( '#//[^\n]*#', '', $day_ma );
t( "🔴 cầu đẩy KHÔNG còn đọc \$hs['chuc_vu'] ở đâu cả",
	false === strpos( $day_ma, 'chuc_vu' ), $day_ma );
t( '🔴 và có khai ô Mã NV', false !== strpos( $day_ma, 'C_MA_NV' ), null );

/* ═══ KẾT ═══════════════════════════════════════════════════════════════════════ */
echo "\n";
if ( $TRUOT ) {
	echo "TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo "  ✗ $x\n"; }
	exit( 1 );
}
echo "ĐẠT: $DAT phép thử\nTất cả phép thử đều đạt.\n";
