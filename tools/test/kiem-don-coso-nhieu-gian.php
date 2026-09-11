<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * CHI PHÍ CƠ SỞ KỸ THUẬT: MỘT ĐỢT LÀ MỘT ĐƠN, MỘT ĐƠN NHIỀU CƠ SỞ.
 *
 * Anh Thắng 11/09/2026: *"tiếp tới chi phí kỹ thuật cơ sở, sẽ giống kiểu chi phí bên POSH,
 * 1 đơn nhiều cơ sở chung 1 đơn"*.
 *
 * =============================================================================================
 * Trước bản này, chi phí cơ sở kỹ thuật là MỘT "sheet" chung xuyên suốt: mọi gian, mọi tháng,
 * mọi khoản dồn vào một chỗ không bao giờ chốt được — nên cũng không bao giờ xin tạm ứng hay
 * quyết toán theo đợt được.
 *
 * 🔴 SỔ CHUNG CŨ KHÔNG ĐƯỢC MẤT, VÀ KHÔNG ĐƯỢC ĐÓNG/XOÁ/ĐỔI TÊN. Dữ liệu đã nằm đó và đã xuất
 *    MISA; đóng nó là khoá luôn lối nhập mà chẳng ai mở lại được đúng chỗ.
 *
 * 🔴 ĐƠN THEO ĐỢT PHẢI ĐÓNG / XOÁ / ĐỔI TÊN ĐƯỢC. Chốt cũ chặn theo LOẠI ('Chi phí cơ sở'), mà
 *    đơn theo đợt cũng mang đúng loại ấy — giữ nguyên chốt cũ là mọi đơn mới đều bất động.
 *
 * 🔴 ĐƠN THEO ĐỢT ĐẦU TIÊN TRONG MỘT SỔ CHƯA CÓ SỔ CHUNG KHÔNG ĐƯỢC BIẾN THÀNH SỔ CHUNG.
 *    "Sổ chung" tra ra bằng "dự án loại Chi phí cơ sở CŨ NHẤT" khi chưa có vết ghim; không ghim
 *    vết trống thì đơn của nhân viên lặng lẽ thành sổ chung — không đóng, không xoá, không báo.
 *
 * 🔴 TỔNG CÁC GIAN PHẢI BẰNG TỔNG ĐƠN. Hạng mục có mục con thì tiền nằm ở CON; gom theo gian mà
 *    cộng cả cha lẫn con là gian nào cũng phình lên, và không ai biết bên nào sai.
 *
 * 🔴 MỤC CON THỪA HƯỞNG GIAN CỦA CHA. Không thừa hưởng thì tiền thật rơi hết vào rổ
 *    "(chưa ghi gian)" — đúng chỗ nó nằm, sai chỗ người ta đi tìm.
 *
 * ⚠️ CHẠY THẬT: bốc hàm thật của plugin ra gọi, không chép lại luật.
 *
 * Chạy: php tools/test/kiem-don-coso-nhieu-gian.php
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

VHCP_Auth::dat_vai_tro( 'Admin', 'KT' );

/* ═══ 1. SỔ SẠCH: KHÔNG TỰ ĐẺ RA SỔ CHUNG ══════════════════════════════════════════════════
 * `la_coso_chung()` bị gọi ngay trong `delete()`. Tra một cái chưa tồn tại mà lại tạo ra nó là
 * xoá một dự án lại sinh thêm một dự án. */
teq( '🔴 sổ sạch: ma_coso_chung() trả rỗng, KHÔNG tự tạo', '', VHCP_DuAn::ma_coso_chung() );
$_ds0 = VHCP_DuAn::list_du_an();
teq( '   và không có dự án nào được sinh ra', 0, count( $_ds0['items'] ) );

/* ═══ 2. 🔴 ĐƠN THEO ĐỢT ĐẦU TIÊN KHÔNG ĐƯỢC THÀNH SỔ CHUNG ════════════════════════════════ */
$r1 = VHCP_DuAn::create_du_an( 'Chi phí cơ sở', 'Chi phí cơ sở T9-2026', 'NV' );
t( 'lập được đơn chi phí cơ sở theo đợt', ! empty( $r1['success'] ), $r1 );
$D1 = $r1['maDA'];
teq( '   đơn mang đúng loại "Chi phí cơ sở" (loại quyết mã tài khoản)', 'Chi phí cơ sở', $r1['loai'] );
t( '🔴 đơn theo đợt ĐẦU TIÊN trong sổ chưa có sổ chung: KHÔNG phải sổ chung — '
	. 'thành sổ chung là đơn của nhân viên bất động, không báo gì',
	false === VHCP_DuAn::la_coso_chung( $D1 ), VHCP_DuAn::la_coso_chung( $D1 ) );
teq( '   ma_coso_chung() vẫn rỗng', '', VHCP_DuAn::ma_coso_chung() );

/* ═══ 3. SỔ CHUNG DỰNG SAU VẪN LÀ SỔ CHUNG ════════════════════════════════════════════════ */
$rc = VHCP_DuAn::ensure_co_so_chung( 'KT' );
$CH = $rc['maDA'];
t( 'mở được sổ chi phí cơ sở CHUNG', ! empty( $rc['success'] ), $rc );
t( '   sổ chung là sổ chung', true === VHCP_DuAn::la_coso_chung( $CH ) );
t( '   đơn theo đợt vẫn KHÔNG phải sổ chung', false === VHCP_DuAn::la_coso_chung( $D1 ) );
$rc2 = VHCP_DuAn::ensure_co_so_chung( 'KT' );
teq( '🔴 mở lần hai trả ĐÚNG sổ cũ, không đẻ thêm sổ chung thứ hai', $CH, $rc2['maDA'] );
teq( '   tên rỗng qua create_du_an cũng về đúng sổ chung ấy', $CH,
	VHCP_DuAn::create_du_an( 'Chi phí cơ sở', '', 'NV' )['maDA'] );
teq( '   mã dự án lạ không phải sổ chung', false, VHCP_DuAn::la_coso_chung( 'DA-khong-co-that' ) );
teq( '   mã rỗng không phải sổ chung', false, VHCP_DuAn::la_coso_chung( '' ) );

/* ═══ 3b. 🔴 TUẦN ĐI CÙNG LỜI GỌI TẠO, KHÔNG PHẢI LỜI GỌI THỨ HAI ════════════════════════
 * Anh Thắng: *"Nếu chi phí cơ sở thì chọn Tuần"*. Tạo xong rồi mới gọi tiếp `set_ky_da()` là
 * hai lượt mạng cho một việc — lượt sau hỏng thì đơn nằm đó KHÔNG có tuần, màn vẫn báo đã tạo. */
$rt = VHCP_DuAn::create_du_an( 'Chi phí cơ sở', 'Chi phí cơ sở tuần 07.09-13.09.2026', 'NV', '2026-09-07', '2026-09-13' );
t( 'lập đơn kèm tuần trong MỘT lời gọi', ! empty( $rt['success'] ), $rt );
$DT = $rt['maDA'];
$kt = VHCP_DuAn::get_du_an( $DT )['kyDA'];
teq( '🔴 khoảng ngày lưu thật, đọc lại đúng ngày đầu tuần', '2026-09-07', $kt['tu'] );
teq( '   và đúng ngày cuối tuần', '2026-09-13', $kt['den'] );
t( '   đơn kèm tuần vẫn KHÔNG phải sổ chung', false === VHCP_DuAn::la_coso_chung( $DT ) );

$truoc = count( VHCP_DuAn::list_du_an()['items'] );
$rn = VHCP_DuAn::create_du_an( 'Chi phí cơ sở', 'Tuần ngược', 'NV', '2026-09-13', '2026-09-07' );
t( '🔴 ngày kết thúc trước ngày bắt đầu: CHỐI', empty( $rn['success'] ), $rn );
teq( '🔴 và KHÔNG đẻ ra đơn rác — kiểm sau khi thêm dòng thì chối cũng muộn',
	$truoc, count( VHCP_DuAn::list_du_an()['items'] ) );

$rk = VHCP_DuAn::create_du_an( 'Chi phí cơ sở', 'Đơn không tuần', 'NV' );
t( 'không truyền tuần vẫn lập được đơn (không ép)', ! empty( $rk['success'] ), $rk );
teq( '   và kỳ để trống, không tự bịa ngày', '', VHCP_DuAn::get_du_an( $rk['maDA'] )['kyDA']['tu'] );

/* ═══ 4. 🔴 BỐN CHỐT: SỔ CHUNG BẤT ĐỘNG, ĐƠN THEO ĐỢT ĐI TRỌN LUỒNG ═══════════════════════ */
t( '🔴 sổ chung KHÔNG đổi tên', empty( VHCP_DuAn::rename_du_an( $CH, 'Tên khác' )['success'] ) );
t( '🔴 sổ chung KHÔNG gửi duyệt',  empty( VHCP_DuAn::submit( $CH )['success'] ) );
t( '🔴 sổ chung KHÔNG đóng',       empty( VHCP_DuAn::close( $CH )['success'] ) );
t( '🔴 sổ chung KHÔNG xoá',        empty( VHCP_DuAn::delete( $CH )['success'] ) );
t( '   và sổ chung vẫn còn nguyên sau bốn lần bị chối', null !== VHCP_DuAn::find( $CH ) );

t( '🔴 đơn theo đợt ĐỔI TÊN được', ! empty( VHCP_DuAn::rename_du_an( $D1, 'Chi phí cơ sở T9-2026 (sửa)' )['success'] ) );
teq( '   tên đổi thật trong sổ', 'Chi phí cơ sở T9-2026 (sửa)', (string) VHCP_DuAn::find( $D1 )['ten'] );

/* ═══ 5. 🔴 MỘT ĐƠN NHIỀU GIAN: GOM THEO GIAN, TỔNG PHẢI KHỚP ═════════════════════════════ */
VHCP_DuAn::add_line( $D1, array( 'noiDung' => 'Sửa điện', 'gian' => 'Gian A', 'duToan' => 5000000, 'thucTe' => 900000 ) );
foreach ( array( array( 'Bóng đèn', 2000000 ), array( 'Dây điện', 3000000 ) ) as $c ) {
	/* 🔴 MỤC CON ĐỂ TRỐNG GIAN — đúng như nhân viên nhập thật: gõ gian ở hạng mục cha rồi
	   thôi. Gán sẵn 'Gian A' cho con là bài kiểm tự đắp cái mà mã phải tự làm. */
	VHCP_DuAn::add_line( $D1, array( 'noiDung' => $c[0], 'capCha' => 'Sửa điện', 'thucTe' => $c[1] ) );
}
VHCP_DuAn::add_line( $D1, array( 'noiDung' => 'Thay khoá', 'gian' => 'Gian B', 'duToan' => 1000000, 'thucTe' => 1200000 ) );
VHCP_DuAn::add_line( $D1, array( 'noiDung' => 'Vệ sinh máy lạnh', 'gian' => 'Gian C', 'duToan' => 800000, 'thucTe' => 800000 ) );
VHCP_DuAn::add_line( $D1, array( 'noiDung' => 'Khoản chưa rõ gian', 'thucTe' => 300000 ) );

/* 🔴 MỘT MỤC CON MANG DỰ TOÁN — ghi thẳng vào bảng vì `line_data()` ép dự toán của mục con về 0.
   Ca này CÓ THẬT ngoài sổ: dữ liệu nạp từ sheet cũ và các dòng lưu trước khi có luật ấy vẫn còn
   số dự toán ở mục con. Cộng nó vào là dự toán của gian phình lên, không khớp tổng đơn — mà
   tổng đơn thì chỉ cộng dự toán của HẠNG MỤC LỚN. */
( function () use ( $D1 ) {
	global $wpdb;
	$t = VHCP_DB::t( 'da_line' );
	$r = VHCP_DB::row( $wpdb->prepare( "SELECT * FROM $t WHERE ma_da=%s AND noi_dung=%s", $D1, 'Dây điện' ) );
	$wpdb->update( $t, array( 'du_toan' => 777000 ), array( 'ma_da' => $D1, 'row_no' => (int) $r['row_no'] ) );
} )();

$d = VHCP_DuAn::get_du_an( $D1 );
t( '   đọc lại được đơn', ! empty( $d['success'] ), $d );
teq( '   đơn cơ sở mang cờ isCoSo', true, $d['isCoSo'] );
teq( '🔴 nhưng KHÔNG phải sổ chung — nếu không, màn giấu hết nút đóng/xoá', false, $d['cosoChung'] );
teq( '   sổ chung thì cờ cosoChung bật', true, VHCP_DuAn::get_du_an( $CH )['cosoChung'] );

$cs = array();
foreach ( $d['theoCoSo'] as $x ) { $cs[ $x['coso'] ] = $x; }
teq( '   đơn rải qua 4 rổ: A · B · C · chưa ghi gian', 4, count( $cs ) );
t( '🔴 Gian A = 5.000.000 thực tế (900k của cha KHÔNG cộng vì đã có mục con) — '
	. 'cộng cả cha lẫn con là gian phình lên, tổng các gian không khớp tổng đơn',
	isset( $cs['Gian A'] ) && 5000000 == $cs['Gian A']['thucTe'],
	isset( $cs['Gian A'] ) ? $cs['Gian A']['thucTe'] : null );
t( '   Gian A dự toán 5.000.000 (dự toán chỉ nằm ở hạng mục lớn)',
	isset( $cs['Gian A'] ) && 5000000 == $cs['Gian A']['duToan'],
	isset( $cs['Gian A'] ) ? $cs['Gian A']['duToan'] : null );
t( '🔴 MỤC CON THỪA HƯỞNG GIAN CỦA CHA: Gian A đếm 3 dòng (cha + 2 con)',
	isset( $cs['Gian A'] ) && 3 === (int) $cs['Gian A']['soDong'],
	isset( $cs['Gian A'] ) ? $cs['Gian A']['soDong'] : null );
t( '   Gian B = 1.200.000', isset( $cs['Gian B'] ) && 1200000 == $cs['Gian B']['thucTe'] );
t( '   Gian C = 800.000',   isset( $cs['Gian C'] ) && 800000 == $cs['Gian C']['thucTe'] );
t( '   dòng chưa ghi gian gom vào rổ tên rỗng, KHÔNG biến mất',
	isset( $cs[''] ) && 300000 == $cs['']['thucTe'], isset( $cs[''] ) ? $cs['']['thucTe'] : null );

$tong_gian = 0;
foreach ( $d['theoCoSo'] as $x ) { $tong_gian += $x['thucTe']; }
t( '🔴 TỔNG CÁC GIAN = TỔNG THỰC TẾ CẢ ĐƠN (7.300.000)',
	$tong_gian == $d['tongThucTe'] && 7300000 == $tong_gian,
	$tong_gian . ' vs ' . $d['tongThucTe'] );
$tong_dt = 0;
foreach ( $d['theoCoSo'] as $x ) { $tong_dt += $x['duToan']; }
t( '   TỔNG DỰ TOÁN CÁC GIAN = TỔNG DỰ TOÁN ĐƠN (6.800.000)',
	$tong_dt == $d['tongDuToan'] && 6800000 == $tong_dt, $tong_dt . ' vs ' . $d['tongDuToan'] );

/* ═══ 6. DANH SÁCH: ĐẾM GIAN + PHÂN BIỆT SỔ CHUNG ════════════════════════════════════════ */
$ds = VHCP_DuAn::list_du_an();
$by = array();
foreach ( $ds['items'] as $x ) { $by[ $x['maDA'] ] = $x; }
teq( '🔴 danh sách đếm đúng 3 gian có tên của đơn (rổ trống không tính là một gian)',
	3, isset( $by[ $D1 ] ) ? (int) $by[ $D1 ]['soCoSo'] : -1 );
teq( '   danh sách đánh dấu sổ chung', true, isset( $by[ $CH ] ) ? $by[ $CH ]['cosoChung'] : null );
teq( '   và KHÔNG đánh dấu đơn theo đợt', false, isset( $by[ $D1 ] ) ? $by[ $D1 ]['cosoChung'] : null );

/* ═══ 7. ĐƠN THEO ĐỢT ĐÓNG / MỞ LẠI / XOÁ ĐƯỢC ══════════════════════════════════════════ */
t( '🔴 đơn theo đợt ĐÓNG được', ! empty( VHCP_DuAn::close( $D1 )['success'] ) );
teq( '   đóng rồi thì trạng thái là Đã đóng', 'Đã đóng', (string) VHCP_DuAn::find( $D1 )['trang_thai'] );
t( '   đơn đã đóng thì chối xoá (phải Mở lại trước)', empty( VHCP_DuAn::delete( $D1 )['success'] ) );
t( '   mở lại được', ! empty( VHCP_DuAn::reopen( $D1 )['success'] ) );
t( '🔴 đơn theo đợt XOÁ được', ! empty( VHCP_DuAn::delete( $D1 )['success'] ) );
t( '   xoá rồi thì không còn trong sổ', null === VHCP_DuAn::find( $D1 ) );
t( '   xoá đơn theo đợt KHÔNG đụng sổ chung', null !== VHCP_DuAn::find( $CH ) );

/* ═══ 8. SỔ CŨ CHƯA CÓ VẾT GHIM: NGÃ VỀ DỰ ÁN 'Chi phí cơ sở' CŨ NHẤT ════════════════════
 * Đây là ca của mọi sổ đang chạy ngoài thật: sổ chung có từ trước, khoá ghim thì chưa. Luật
 * tra ngã về phải trùng ĐÚNG thứ `ensure_co_so_chung()` vẫn chọn trước bản này, nếu không thì
 * cài bản mới lên là sổ chung của anh Thắng đổi chủ. */
VHCP_Meta::del( VHCP_DuAn::MK_COSO_CHUNG );
teq( '🔴 mất vết ghim: ngã về dự án "Chi phí cơ sở" CŨ NHẤT, đúng sổ chung sẵn có',
	$CH, VHCP_DuAn::ma_coso_chung() );
teq( '   và ghim lại luôn, lần sau khỏi tra', $CH, trim( (string) VHCP_Meta::get( VHCP_DuAn::MK_COSO_CHUNG, '' ) ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════ */
echo "\n";
if ( count( $TRUOT ) ) {
	echo "❌ TRƯỢT " . count( $TRUOT ) . " / " . ( $DAT + count( $TRUOT ) ) . "\n";
	foreach ( $TRUOT as $x ) { echo "   • $x\n"; }
	exit( 1 );
}
echo "✅ ĐẠT $DAT / $DAT — đơn chi phí cơ sở: một đợt một đơn, một đơn nhiều gian\n";
exit( 0 );
