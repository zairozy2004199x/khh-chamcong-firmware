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

/* 🔴 HAI DANH MỤC KHÔNG TRÙNG NHAU. Nhân sự khai "Phòng Kỹ Thuật", chi phí chỉ biết "Kỹ thuật" —
   hai chuỗi khác nhau, `bo_phan_chuan()` so chữ chứ không đoán nghĩa. Chưa khai bản đồ thì cầu
   đẩy phải trả RỖNG, và rỗng bên kia nghĩa là KHÔNG BÓ: người ấy xem được sổ của mọi mảng.
   ⚠️ Đây là phép canh cái nguy hiểm hơn hẳn: rỗng là NỚI quyền, không phải mất quyền. */
$hsd = VHCC_DayChiPhi::ho_so_day( 'P002' );
t( 'dựng được hồ sơ để đẩy', is_array( $hsd ), $hsd );
teq( '🔴 phòng ban bên kia KHÔNG hiểu -> rỗng, không gửi bừa', '', $hsd['bo_phan'] );
t( '🔴 và KHÔNG mượn chức vụ ("Ca trưởng")', 'Ca trưởng' !== $hsd['bo_phan'], $hsd['bo_phan'] );
teq( '🔴 mang theo cả Mã NV — sợi dây nối hai bên', 'P002', $hsd['ma_nv'] );

/* ⚠️ KHAI BẢN ĐỒ PHÒNG BAN RỒI THÌ PHẢI ĂN NGAY. Đây là đường DUY NHẤT bó được người có phòng
   ban mang tên bên chi phí không biết — mà đó là gần như cả sổ. */
VHCC_DayChiPhi::dat_ban_do_bp( $ad, 'Phòng Kỹ Thuật', 'Kỹ thuật' );
teq( '🔴 khai bản đồ phòng ban -> gửi sang đúng bộ phận chi phí', 'Kỹ thuật',
	VHCC_DayChiPhi::ho_so_day( 'P002' )['bo_phan'] );
t( '⚠️ khai tên bên chi phí KHÔNG có thì bị chối',
	empty( VHCC_DayChiPhi::dat_ban_do_bp( $ad, 'Phòng Kho Hàng', 'Phòng Tào Lao' )['ok'] ), null );

/* 🔴 Ô BỘ PHẬN ĐI TRƯỚC CHỨC VỤ. Hồ sơ khai đủ cả hai, cả hai đều là tên bên kia hiểu — phải
   lấy Bộ phận. Anh Thắng: *"quyết định bộ phận do nhân sự quyết định"*; chức vụ chỉ là chữ mô
   tả việc, để nó thắng là trả quyền quyết định về đúng cái ô không ai coi là sơ đồ tổ chức. */
VHCC_NhanSu::luu_ho_so( $ad, array( 'ma_nv' => 'P004', 'ho_ten' => 'Anh Tư',
	'cua_hang' => 'FARM PHAN THIẾT', 'chuc_vu' => 'Marketing',
	'pin_dang_nhap' => '7711', 'vai_tro' => 'Nhân viên' ) );
VHCC_NhanSu::dat_mang_bo_phan( $ad, 'P004', '', 'Phòng Kỹ Thuật' );
teq( '🔴 khai cả hai -> ô Bộ phận THẮNG ô Chức vụ', 'Kỹ thuật',
	VHCC_DayChiPhi::ho_so_day( 'P004' )['bo_phan'] );

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
/* ══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 PHÉP NÀY TRƯỚC ĐÂY ĐÒI NGƯỢC LẠI — và nó chốt cứng một hành vi nay đã cấm.
 * ══════════════════════════════════════════════════════════════════════════════════════════
 * Bản cũ: `teq('ô Bộ phận bên chi phí = phòng ban đã dịch qua bản đồ', 'Kỹ thuật', …)`.
 *
 * Anh Thắng 20/09/2026: *"việc đẩy nhân sự sang chỉ là để đăng nhập. Sau phân quyền cho bên chi
 * phí quyết định. Để tránh râu ông này cắm bà kia"*. Lượt đẩy nay chỉ mang TÊN · PIN · MÃ NV.
 *
 * ⚠️ BẢN ĐỒ PHÒNG BAN (`dat_ban_do_bp`) VẪN GIỮ và vẫn được canh ở mấy phép trên — nó còn dùng
 *    cho màn SOÁT (bày chỗ lệch giữa hai bên để người ta tự xử). Chỉ mỗi việc GHI XUỐNG là bỏ.
 * ══════════════════════════════════════════════════════════════════════════════════════════ */
teq( '🔴 lượt đẩy KHÔNG ghi ô Bộ phận — bên chi phí tự khai', '',
	(string) $hang['boPhan'] );
/* ⚠️ ĐỌC Ô THÔ, KHÔNG ĐỌC QUA `get_users()`. Hàm ấy quy ô vai TRỐNG về 'Nhân viên' lúc bày ra
   (dòng `$r[2] !== '' ? $r[2] : 'Nhân viên'`), nên hỏi nó thì không phân biệt được "đẩy có ghi
   vai" với "đẩy để trống rồi bị quy về mặc định" — đúng hai thứ phép này sinh ra để tách. */
$_o_vai = '';
foreach ( VHCP_Cfg::read( VHCP_Cfg::USER ) as $_r ) {
	$_r = array_values( (array) $_r );
	if ( 0 === strcasecmp( trim( (string) $_r[0] ), 'Anh Hai' ) ) { $_o_vai = trim( (string) $_r[2] ); }
}
teq( '🔴 và cũng KHÔNG ghi ô Vai trò — ai được làm gì là việc của bên chi phí', '', $_o_vai );
/* ⚠️ Nhưng trống KHÔNG phải bị khoá: `login()` và `get_users()` đều quy về 'Nhân viên', nên
   người mới đẩy sang vẫn đăng nhập được — đúng nghĩa "đẩy sang chỉ để đăng nhập". */
teq( '   trống nhưng vẫn vào được: hệ quy về Nhân viên', 'Nhân viên', (string) $hang['vaiTro'] );
teq( '🔴 ô Mã NV bên chi phí được điền',     'P002', (string) $hang['maNv'] );
teq( '   PIN đi theo',                        '7788', (string) $hang['pin'] );

/* Sửa phòng ban bên nhân sự -> `dong_bo()` kéo sang, không phải đẩy lại tay. */
VHCC_DayChiPhi::dat_ban_do_bp( $ad, 'Phòng Marketing', 'Marketing' );
VHCC_NhanSu::dat_mang_bo_phan( $ad, 'P002', '', 'Phòng Marketing' );
VHCC_DayChiPhi::dong_bo( 'P002' );
/* 🔴 VÀ ĐỔI BÊN NHÂN SỰ CŨNG KHÔNG KÉO SANG. Đây mới là vế nguy: lượt đồng bộ chạy lại nhiều
   lần, nên nếu nó ghi đè thì kế toán hạ vai/đổi bộ phận hôm nay, mai nó tự về như cũ — sửa mà
   không giữ được, và không ai hiểu vì sao. */
teq( '🔴 đổi phòng ban bên Nhân sự KHÔNG kéo sang bản sao bên Chi phí', '',
	(string) hang_cp( 'Anh Hai' )['boPhan'] );

/* ⚠️ NHỮNG CỘT KẾ TOÁN TỰ KHAI PHẢI CÒN NGUYÊN. Sổ nhân sự không biết TK Có · Mã đối tượng ·
   Đơn vị, và không được đoán. Đẩy mà xoá chúng là kế toán mất bảng khai mà không ai báo. */
$rows = VHCP_Cfg::read( VHCP_Cfg::USER );
foreach ( $rows as $i => $r0 ) {
	$r0 = array_values( (array) $r0 );
	if ( 0 === strcasecmp( trim( (string) $r0[0] ), 'Anh Hai' ) ) {
		$r0[2] = 'Kế toán cá nhân'; $r0[5] = 'DT999'; $r0[6] = 'Marketing'; $r0[7] = 'POSH';
		$rows[ $i ] = $r0;
	}
}
VHCP_Cfg::write( VHCP_Cfg::USER, $rows );
VHCP_Cfg::clear_cache();
VHCC_DayChiPhi::dong_bo( 'P002' );
$h3 = hang_cp( 'Anh Hai' );
teq( '⚠️ Mã đối tượng kế toán khai vẫn còn', 'DT999', (string) $h3['maDt'] );
teq( '⚠️ Đơn vị kế toán khai vẫn còn',       'POSH',  (string) $h3['donVi'] );
/* 🔴 HAI PHÉP CỐT TỬ CỦA LUẬT MỚI. Kế toán đã phân vai và bộ phận; lượt đồng bộ chạy lại phải
   GIỮ NGUYÊN. Đè lại là công phân quyền của họ mất sạch sau một lượt đồng bộ mà không câu nào
   báo — và vì đồng bộ chạy nhiều lần, nó mất đi mất lại. */
teq( '🔴 vai trò kế toán đã phân vẫn còn sau lượt đồng bộ', 'Kế toán cá nhân', (string) $h3['vaiTro'] );
teq( '🔴 bộ phận kế toán đã khai vẫn còn sau lượt đồng bộ',  'Marketing',      (string) $h3['boPhan'] );
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
/* 🔴 CHỨC VỤ VẪN ĐƯỢC TRA, NHƯNG ĐỨNG SAU VÀ PHẢI QUA CỬA `bo_phan_hop_le()`.
   Bản trước của bài này đòi gỡ sạch `chuc_vu` khỏi cầu đẩy. Sai ở chỗ: cái hại không nằm ở việc
   ĐỌC chức vụ, nó nằm ở việc GỬI THẲNG một chuỗi tự do sang bên kia — và bên kia quy mọi tên lạ
   về rỗng = không bó. Lọc qua `bo_phan_hop_le()` thì chức vụ chỉ còn đi tiếp khi nó đúng là một
   bộ phận bên chi phí, tức không còn là đường nới quyền; giữ nó làm đường lui cho hồ sơ cũ chưa
   ai khai phòng ban. Phép tĩnh dưới đây canh THỨ TỰ: bộ phận đọc trước chức vụ. */
t( '🔴 cầu đẩy đọc ô bo_phan TRƯỚC ô chuc_vu',
	false !== strpos( $day_ma, "\$hs['bo_phan']" )
		&& strpos( $day_ma, "\$hs['bo_phan']" ) < strpos( $day_ma, "\$hs['chuc_vu']" ), null );
/* 🔴 MỌI LẦN ĐỌC `chuc_vu` ĐỀU PHẢI BỌC TRONG `bo_phan_hop_le()`. Đếm hai con số và bắt chúng
   BẰNG NHAU: một lần đọc lọt ra ngoài là một đường gửi chuỗi tự do sang chi phí — đúng cái lỗi
   bài này sinh ra để dẹp. Đếm thì bắt được cả nhánh mới thêm về sau, còn tìm một mẫu cố định
   thì chỉ bắt được đúng cái mẫu ấy. */
$dong_cv = array();
foreach ( explode( "\n", $day_ma ) as $d_cv ) {
	if ( false !== strpos( $d_cv, "\$hs['chuc_vu']" ) ) { $dong_cv[] = trim( $d_cv ); }
}
$lot = array();
foreach ( $dong_cv as $d_cv ) {
	if ( false === strpos( $d_cv, 'bo_phan_hop_le(' ) ) { $lot[] = $d_cv; }
}
t( '🔴 mọi lần đọc chuc_vu đều đi qua bo_phan_hop_le()', ! $lot, $lot );
t( '   và có ít nhất một lần đọc (kẻo không còn dòng nào rồi khoe sạch)', count( $dong_cv ) > 0, null );
t( '🔴 và có khai ô Mã NV', false !== strpos( $day_ma, 'C_MA_NV' ), null );

/* ═══ 4. 🔴 ĐẨY ĐÍCH DANH MỘT NGƯỜI — CHỖ MÀ ANH THẮNG BẢO "KHÔNG CÓ" ═════════════
 * Anh Thắng 13/09/2026: *"đang có 1 nhân viên mới, cần add vào trang chi phí để nhập báo cáo,
 * nhưng không có chỗ"*. Bản 3.77.0 gộp năm cột quyền thành một cột CHỈ ĐỌC; ba cột quyền trang
 * còn đường riêng trong khối "sửa ▾", nhưng hai cột ĐẨY NGƯỜI (Ghế · Chi phí) thì mất hẳn — chỉ
 * còn nút "Đẩy hết N người" đi theo luật CẢ PHÒNG, đúng thứ anh vừa ngừng dùng vì dính chung.
 * ═══════════════════════════════════════════════════════════════════════════════════ */
$ns = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-trang-ns.php' );
$ns_ma = preg_replace( '#/\*.*?\*/#s', '', $ns );
$ns_ma = preg_replace( '#//[^\n]*#', '', $ns_ma );
/* ⚠️ PHÉP TĨNH PHẢI CHỈ ĐÚNG CHỖ, KHÔNG CHỈ "CÓ CHỮ ẤY Ở ĐÂU ĐÓ". Bản nháp của hai phép này
   chỉ tìm chuỗi `name="day_1"` và `isset( $_POST['day_1'] )` trong cả tệp — gỡ nút ra khỏi chip
   thì biến `$nut` vẫn còn đó, gỡ dòng dispatcher thì thân hàm xử lý vẫn đọc `$_POST['day_1']`.
   Cả hai đột biến SỐNG. Nay đòi đúng cái chip TRẢ VỀ nút, và đúng dòng `elseif` của dispatcher. */
t( '🔴 chip Ghế/Chi phí TRẢ VỀ cả cái nút đẩy riêng một người',
	false !== strpos( $ns_ma, "name=\"day_1\"" )
		&& (bool) preg_match( '#return .{0,400}\$nut\s*\.\s*.</span>.;#s', $ns_ma ), null );
/* ⚠️ NÚT PHẢI ĐỔI CHIỀU THEO TRẠNG THÁI. Luôn gửi "mo" thì bấm vào người đã có tài khoản là
   đẩy lại lần nữa — không gỡ được ai bằng màn hình, mà gỡ mới là nửa cần thiết hơn: đẩy nhầm
   một người vào sổ có ngăn tiền thì phải rút ra được ngay. */
t( '🔴 nút đổi chiều theo trạng thái (đã có -> gỡ, chưa có -> đẩy)',
	(bool) preg_match( "#\\\$da \\? '' : 'mo'#", $ns_ma ), null );
t( '🔴 và cái nút ấy có đường đi: dispatcher có nhánh riêng cho `day_1`',
	(bool) preg_match( "#elseif \\( isset\\( \\\$_POST\\['day_1'\\] \\) \\) \\{ \\\$viec_gui = 'day_mot'#", $ns_ma ), null );
t( '🔴 việc `day_mot` được khai vào danh sách trắng',
	false !== strpos( $ns_ma, "'day_mot' === \$viec" ), null );
$than = substr( $ns_ma, (int) strpos( $ns_ma, 'function viec_day_mot' ), 2600 );
t( '⚠️ và nó KHÔNG đi qua luật nhóm (không gọi chenh_day)',
	false === strpos( $than, 'chenh_day' ), null );
/* ⚠️ Bó bộ phận phải được KIỂM ngay trong lượt đẩy một người, không thì cái nút tiện này lại
   thành đường nới quyền mới: đẩy xong, không bó, không ai báo. */
t( '🔴 đẩy một người xong có soi bo_phan_day() để cảnh báo',
	false !== strpos( $than, 'bo_phan_day' ), null );

/* 🔴 VÀ MỘT PHÉP HÀNH VI, KHÔNG CHỈ SOI CHỮ. Ba đột biến đầu tiên của bài này đều sống sót qua
   phép tĩnh: chữ vẫn còn trong tệp, chỉ là đường đi bị cắt. Gọi thẳng `lam_viec('day_mot')` thì
   cắt ở đâu cũng lộ. */
VHCC_NhanSu::luu_ho_so( $ad, array( 'ma_nv' => 'P005', 'ho_ten' => 'Anh Năm',
	'cua_hang' => 'FARM PHAN THIẾT', 'chuc_vu' => 'Thu ngân',
	'pin_dang_nhap' => '7755', 'vai_tro' => 'Nhân viên' ) );
VHCC_NhanSu::dat_mang_bo_phan( $ad, 'P005', '', 'Phòng Kho Hàng' );
t( 'chưa bấm thì P005 chưa có bên chi phí', ! VHCC_DayChiPhi::da_day( 'P005' ), null );

$_POST = array( 'day_1' => VHCC_DayChiPhi::COT . '|P005|mo' );
$bao1 = VHCC_TrangNS::lam_viec( 'day_mot', $ad );
$_POST = array();
t( '🔴 bấm "+" trên chip -> P005 CÓ tài khoản bên chi phí', VHCC_DayChiPhi::da_day( 'P005' ), $bao1 );
t( '   và báo về là "ok", không phải lỗi', ! empty( $bao1[0]['ok'] ), $bao1 );
/* ⚠️ "Phòng Kho Hàng" chưa khai bản đồ -> gửi sang RỖNG = không bó = xem sổ mọi mảng. Nút tiện
   mà im lặng chỗ này thì chính nó thành đường nới quyền mới. */
t( '🔴 và NÓI RA rằng người này chưa bị bó bộ phận',
	! empty( $bao1[0]['ok'] ) && false !== strpos( $bao1[0]['ok'], 'CHƯA bị bó' ), $bao1 );

$_POST = array( 'day_1' => VHCC_DayChiPhi::COT . '|P005|' );
$bao2 = VHCC_TrangNS::lam_viec( 'day_mot', $ad );
$_POST = array();
t( '🔴 bấm "−" -> gỡ lại được, không kẹt một chiều', ! VHCC_DayChiPhi::da_day( 'P005' ), $bao2 );

/* ⚠️ Người CÓ bó thì đừng doạ. Báo sai chỗ cũng là báo hỏng: người đọc quen bị doạ thì lần sau
   họ bỏ qua đúng lúc cần đọc. */
$_POST = array( 'day_1' => VHCC_DayChiPhi::COT . '|P004|mo' );
$bao3 = VHCC_TrangNS::lam_viec( 'day_mot', $ad );
$_POST = array();
t( '⚠️ người ĐÃ bó thì không bị doạ nhầm',
	! empty( $bao3[0]['ok'] ) && false === strpos( $bao3[0]['ok'], 'CHƯA bị bó' ), $bao3 );
t( '   và nói rõ bộ phận gửi sang là gì',
	! empty( $bao3[0]['ok'] ) && false !== strpos( $bao3[0]['ok'], 'Kỹ thuật' ), $bao3 );

/* 🔴 VÀ NGƯỜI KHÔNG CÓ QUYỀN THÌ KHÔNG BẤM ĐƯỢC. Sổ người dùng bên chi phí là sổ có NGĂN TIỀN;
   thêm một nút tiện tay mà quên chốt là mở một cửa không ai gác. `VHCC_DayChiPhi::dat()` có chốt
   riêng của nó, nhưng chốt ở tầng trang phải còn: hai tầng thì gỡ nhầm một tầng vẫn chưa thủng. */
$nv_thuong = array( 'name' => 'Nhân viên thường', 'ma_nv' => 'NV9', 'role' => 'Nhân viên' );
VHCC_NhanSu::luu_ho_so( $ad, array( 'ma_nv' => 'P006', 'ho_ten' => 'Anh Sáu',
	'cua_hang' => 'FARM PHAN THIẾT', 'chuc_vu' => 'Bảo vệ',
	'pin_dang_nhap' => '7766', 'vai_tro' => 'Nhân viên' ) );
$_POST = array( 'day_1' => VHCC_DayChiPhi::COT . '|P006|mo' );
$bao4 = VHCC_TrangNS::lam_viec( 'day_mot', $nv_thuong );
$_POST = array();
t( '🔴 nhân viên thường bấm "+" -> bị chối', ! empty( $bao4[0]['loi'] ), $bao4 );
/* ⚠️ PHÉP THẬT LÀ PHÉP NÀY. Chốt ở tầng trang và chốt trong `VHCC_DayChiPhi::dat()` là HAI tầng
   cùng canh một cửa; gỡ một tầng thì tầng kia vẫn đỡ, nên soi "tầng nào chối" là soi cái đổi
   được mà không hỏng gì. Cái không được phép đổi là KẾT QUẢ: người ấy không có mặt bên kia. */
t( '🔴 và P006 KHÔNG lọt sang sổ chi phí', ! VHCC_DayChiPhi::da_day( 'P006' ), null );

/* ⚠️ CHIỀU LẠ PHẢI BỊ CHỐI THẲNG, đừng đoán. Giá trị nút do trình duyệt gửi lên — sửa được bằng
   tay. Thả một chuỗi lạ xuống `dat()` thì nó rơi vào nhánh "khác 'mo'" tức GỠ: bấm nhầm một
   tham số là âm thầm rút tài khoản của người ta ra. */
$_POST = array( 'day_1' => VHCC_DayChiPhi::COT . '|P004|xoa_sach' );
$bao5 = VHCC_TrangNS::lam_viec( 'day_mot', $ad );
$_POST = array();
t( '🔴 chiều lạ ("xoa_sach") bị chối, không hiểu thành "gỡ"', ! empty( $bao5[0]['loi'] ), $bao5 );
t( '   và P004 vẫn còn nguyên bên chi phí', VHCC_DayChiPhi::da_day( 'P004' ), null );

/* ═══ KẾT ═══════════════════════════════════════════════════════════════════════ */
echo "\n";
if ( $TRUOT ) {
	echo "TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo "  ✗ $x\n"; }
	exit( 1 );
}
echo "ĐẠT: $DAT phép thử\nTất cả phép thử đều đạt.\n";
