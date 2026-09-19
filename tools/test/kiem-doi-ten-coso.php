<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * ĐỔI TÊN / GỘP MỘT CƠ SỞ — PHẢI QUÉT HẾT MỌI CHỖ CÓ TÊN CƠ SỞ TRONG DỮ LIỆU.
 *
 * Anh Thắng 19/09/2026: *"nhân viên lỡ tạo cơ sở ảo, giờ làm sao chuyển qua cơ sở, vì đã nhập
 * dữ liệu"*. Cơ sở ảo ấy sinh ra từ lượt đẩy nhân sự cũ: nó mang MÃ cửa hàng (`FZ_SC_VIVO_T4`)
 * thay vì TÊN gian hàng, nên không khớp danh mục nào và người mang nó không thấy đơn.
 *
 * =============================================================================================
 * 🔴 BÀI NÀY SINH RA VÌ MỘT CHỖ BỎ SÓT — VÀ TRƯỚC NAY KHÔNG CÓ BÀI NÀO CANH HÀM ẤY
 * =============================================================================================
 * `COSO_BANG` vốn là danh sách TÊN BẢNG, ngầm hiểu cột nào cũng tên `coso`. Bảng `da_line` giữ
 * đúng loại giá trị ấy — tên gian hàng, chọn từ cùng một danh mục — nhưng dưới tên cột `gian`,
 * nên nó rơi ra ngoài CẢ HAI đường:
 *   · `coso_la()` không bao giờ thấy cơ sở lạ chỉ dùng ở dự án -> không ai biết nó tồn tại;
 *   · `doi_ten_coso()` đổi xong vẫn để nguyên dòng dự án -> tiền của một gian tách làm đôi,
 *     nửa mang tên mới nửa mang tên cũ, mà mọi màn đều trông như đã đổi xong.
 *
 * Hỏng im lặng theo hướng tệ nhất: người ta TIN là đã dọn sạch, rồi vài tuần sau mới thấy lệch
 * lúc đối chiếu tiền — khi đã không còn nhớ lượt đổi tên nào gây ra.
 *
 * Chạy: php tools/test/kiem-doi-ten-coso.php
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

global $wpdb;
VHCP_Auth::dat_vai_tro( 'Admin', 'Sếp' );

const AO   = 'FZ_SC_VIVO_T4';      // mã cửa hàng bên nhân sự — KHÔNG phải tên gian
const THAT = 'FUNZONE ADVENTURE';  // gian có thật trong danh mục

/* Danh mục: chỉ có gian thật. Cơ sở ảo cố ý KHÔNG khai — đúng cảnh anh Thắng đang gặp. */
VHCP_Cfg::write( VHCP_Cfg::COSO, array( array( THAT, '', '', '', '', '' ) ) );
/* Một nhân viên đang bị phân vào cơ sở ảo, kèm một gian thật khác để canh chuyện tách chuỗi. */
VHCP_Cfg::write( VHCP_Cfg::USER, array(
	array( 'Mỹ Tiên', '1111', 'Nhân viên', AO . ', ' . THAT, '', '', '', '', '' ),
) );
VHCP_Cfg::clear_cache();

/* Dữ liệu đã nhập, rải đúng năm chỗ có tên cơ sở. */
$wpdb->insert( VHCP_DB::t( 'tamung' ), array( 'ma_don' => 'D1', 'coso' => AO, 'so' => 100 ) );
$wpdb->insert( VHCP_DB::t( 'chiphi' ), array( 'id' => 'C1', 'ma_don' => 'D1', 'coso' => AO, 'thanh_tien' => 200 ) );
$wpdb->insert( VHCP_DB::t( 'so_chi' ), array( 'id' => 'S1', 'coso' => AO, 'so_tien' => 300 ) );
$wpdb->insert( VHCP_DB::t( 'mk_don' ), array( 'ma' => 'M1', 'coso' => AO ) );
$wpdb->insert( VHCP_DB::t( 'da_line' ), array( 'ma_da' => 'DA1', 'row_no' => 5, 'gian' => AO, 'thanh_tien' => 400 ) );
/* Một dòng dự án của gian THẬT — để chắc lượt đổi không quét bừa sang dòng không liên quan. */
$wpdb->insert( VHCP_DB::t( 'da_line' ), array( 'ma_da' => 'DA1', 'row_no' => 6, 'gian' => THAT, 'thanh_tien' => 500 ) );

/* ═══ 1. CƠ SỞ LẠ PHẢI ĐƯỢC KỂ RA — KỂ CẢ KHI NÓ CHỈ NẰM Ở DỰ ÁN ═══════════════════ */
$_la = array();
foreach ( VHCP_Cfg::coso_la() as $x ) { $_la[ $x['ten'] ] = $x; }
t( '🔴 cơ sở ảo bị phát hiện', isset( $_la[ AO ] ), array_keys( $_la ) );
t( '   và đếm cả dòng DỰ ÁN (cột `gian`, không phải `coso`)',
	isset( $_la[ AO ]['dong']['da_line'] ) && 1 === $_la[ AO ]['dong']['da_line'],
	isset( $_la[ AO ] ) ? $_la[ AO ]['dong'] : null );
t( '   gian ĐÃ KHAI thì không bị kể là lạ', ! isset( $_la[ THAT ] ), array_keys( $_la ) );

/* ═══ 2. GỘP VỀ GIAN THẬT ═══════════════════════════════════════════════════════════ */
$r = VHCP_Cfg::doi_ten_coso( AO, THAT );
t( '🔴 gộp chạy được', ! empty( $r['success'] ), $r );

$dem = function ( $bang, $cot, $ten ) use ( $wpdb ) {
	$t = VHCP_DB::t( $bang );
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE $cot=%s", $ten ) );
};
foreach ( array( 'tamung' => 'coso', 'chiphi' => 'coso', 'so_chi' => 'coso', 'mk_don' => 'coso' ) as $b => $c ) {
	teq( "   bảng `$b` không còn dòng nào mang cơ sở ảo", 0, $dem( $b, $c, AO ) );
}
/* 🔴 ĐÂY LÀ PHÉP CỐT TỬ — chỗ bị bỏ sót. */
teq( '🔴 DỰ ÁN cũng hết dòng mang cơ sở ảo (cột `gian`)', 0, $dem( 'da_line', 'gian', AO ) );
teq( '🔴 và dòng dự án ấy đã sang gian thật — không biến mất', 2, $dem( 'da_line', 'gian', THAT ) );
teq( '   số dòng báo về cho bảng dự án', 1, isset( $r['dong']['da_line'] ) ? (int) $r['dong']['da_line'] : -1 );

/* ═══ 3. DANH SÁCH CƠ SỞ CỦA NHÂN VIÊN ═════════════════════════════════════════════
 * ⚠️ Chuỗi ngăn bằng dấu phẩy: phải thay ĐÚNG phần tử. Gộp xong người này chỉ còn một gian,
 *    và không được để lại một mục trùng hay một dấu phẩy lơ lửng. */
$_u = VHCP_Cfg::read( VHCP_Cfg::USER );
$_ds = isset( $_u[0][3] ) ? (string) $_u[0][3] : '';
t( '🔴 ô Cơ sở của nhân viên hết mã ảo', false === mb_strpos( $_ds, AO ), $_ds );
t( '   và vẫn giữ gian thật', false !== mb_strpos( $_ds, THAT ), $_ds );

/* ═══ 4. GỘP THÌ KHÔNG ĐẺ THÊM DÒNG DANH MỤC ═══════════════════════════════════════
 * 🔴 Cơ sở ảo không có dòng nào trong bảng Cấu hình, nên lượt gộp phải KHÔNG đụng bảng ấy.
 *    Thêm một dòng cho tên đích là danh mục có hai dòng cùng tên — tiền một gian tách làm đôi
 *    ở mọi bảng gom, đúng thứ việc này sinh ra để dọn. */
teq( '🔴 gộp không sửa dòng nào trong danh mục cơ sở', 0, (int) $r['cauHinh'] );
teq( '   danh mục vẫn đúng một dòng', 1, count( VHCP_Cfg::read( VHCP_Cfg::COSO ) ) );

/* ═══ 5. CỬA VÀO ═══════════════════════════════════════════════════════════════════ */
$src = (string) @file_get_contents( $goc . '/wordpress/vhcp-chi-phi/includes/class-vhcp-api.php' );
t( "🔴 'doiTenCoSo' có khai ở cửa API",
	false !== strpos( $src, "'doiTenCoSo'" )
	&& false !== strpos( $src, "array( 'VHCP_Cfg', 'doi_ten_coso' )" ) );
$r2 = VHCP_Cfg::doi_ten_coso( THAT, THAT );
t( 'đổi sang chính nó thì chối', empty( $r2['success'] ), $r2 );
$r3 = VHCP_Cfg::doi_ten_coso( '', THAT );
t( 'thiếu tên cũ thì chối', empty( $r3['success'] ), $r3 );

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * PHẦN B — ĐỔI CƠ SỞ CỦA **MỘT ĐƠN** (`VHCP_Don::doi_coso_don`)
 *
 * Anh Thắng 19/09/2026, sau khi thấy công cụ đổi tên hàng loạt: *"cho quyền admin đổi đơn sang
 * cơ sở khác là được"* — hẹp hơn, và đúng hơn cho ca một đơn lạc chỗ.
 *
 * 🔴 HAI VIỆC KHÁC NHAU, ĐỪNG GỘP CỬA. `doi_ten_coso()` đổi MỌI dòng mang tên ấy ở mọi đơn của
 *    mọi người — đúng khi dọn hẳn một cơ sở ảo khỏi hệ, quá tay khi chỉ một đơn lập nhầm.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const THAT2 = 'FUNZONE VŨNG TÀU';
VHCP_Cfg::write( VHCP_Cfg::COSO, array(
	array( THAT, '', '', '', '', '' ),
	array( THAT2, '', '', '', '', '' ),
) );
VHCP_Cfg::clear_cache();

$dung_don = function ( $ma, $cs, $tien ) use ( $wpdb ) {
	$wpdb->insert( VHCP_DB::t( 'don' ), array( 'ma_don' => $ma, 'ky' => 'T9/2026', 'trang_thai' => 'Nháp' ) );
	$wpdb->insert( VHCP_DB::t( 'chiphi' ), array( 'id' => $ma . '_c', 'ma_don' => $ma, 'coso' => $cs, 'thanh_tien' => $tien ) );
	$wpdb->insert( VHCP_DB::t( 'tamung' ), array( 'ma_don' => $ma, 'coso' => $cs, 'so' => $tien ) );
};

/* ═══ B1. CHỈ ADMIN ════════════════════════════════════════════════════════════════
 * 🔴 Nó dời TIỀN ĐÃ NHẬP sang sổ của gian khác, mà sổ gian là thứ người ta đối chiếu hằng
 *    tuần. Cùng bậc với xoá đơn, không phải với sửa một dòng. */
$dung_don( 'DX1', AO, 1000 );
VHCP_Auth::dat_vai_tro( 'Kế toán cá nhân', 'Chị Nhân' );
$rb = VHCP_Don::doi_coso_don( 'DX1', THAT );
t( '🔴 Kế toán KHÔNG đổi được cơ sở của đơn', empty( $rb['success'] ), $rb );
VHCP_Auth::dat_vai_tro( 'Nhân viên', 'Mỹ Tiên' );
$rb2 = VHCP_Don::doi_coso_don( 'DX1', THAT );
t( '   Nhân viên càng không', empty( $rb2['success'] ), $rb2 );
VHCP_Auth::dat_vai_tro( 'Admin', 'Sếp' );

/* ═══ B2. KHÔNG NHẬN TÊN NGOÀI DANH MỤC ════════════════════════════════════════════
 * 🔴 Gõ tay chính là cách đẻ ra con cơ sở ảo đang phải dọn. Mở lại đúng cửa ấy ngay trong công
 *    cụ dọn nó thì công cụ này vô nghĩa. */
$rb3 = VHCP_Don::doi_coso_don( 'DX1', 'GIAN_MA_KHONG_AI_KHAI' );
t( '🔴 cơ sở đích ngoài danh mục thì chối', empty( $rb3['success'] ), $rb3 );
t( '   và câu chối chỉ đúng chỗ phải khai',
	! empty( $rb3['error'] ) && false !== mb_strpos( (string) $rb3['error'], 'danh mục' ), $rb3 );

/* ═══ B3. ĐỔI ĐƯỢC — TIỀN ĐI THEO CẢ HAI BẢNG ══════════════════════════════════════ */
$rb4 = VHCP_Don::doi_coso_don( 'DX1', THAT, '', 'Lập nhầm cơ sở' );
t( '🔴 Admin đổi được', ! empty( $rb4['success'] ), $rb4 );
teq( '   dòng chi đã sang gian thật', 1, (int) $wpdb->get_var( $wpdb->prepare(
	'SELECT COUNT(*) FROM ' . VHCP_DB::t( 'chiphi' ) . ' WHERE ma_don=%s AND coso=%s', 'DX1', THAT ) ) );
teq( '   không còn dòng chi nào ở cơ sở ảo', 0, (int) $wpdb->get_var( $wpdb->prepare(
	'SELECT COUNT(*) FROM ' . VHCP_DB::t( 'chiphi' ) . ' WHERE ma_don=%s AND coso=%s', 'DX1', AO ) ) );
/* 🔴 TẠM ỨNG PHẢI ĐI THEO. Đổi mỗi dòng chi thì đầu đơn hiện gian mới còn số tạm ứng vẫn treo
   ở gian cũ — đối chiếu thừa/thiếu của CẢ HAI gian cùng sai. */
teq( '🔴 dòng tạm ứng cũng sang theo', '1000', (string) $wpdb->get_var( $wpdb->prepare(
	'SELECT so FROM ' . VHCP_DB::t( 'tamung' ) . ' WHERE ma_don=%s AND coso=%s', 'DX1', THAT ) ) );
teq( '   báo về đúng số dòng chi', 1, (int) $rb4['dongChi'] );

/* ═══ B4. TẠM ỨNG PHẢI CỘNG DỒN, KHÔNG ĐỤNG KHOÁ ═══════════════════════════════════
 * ⚠️ `tamung` có khoá DUY NHẤT (ma_don, coso). Đơn đang có dòng tạm ứng cho CẢ tên cũ lẫn tên
 *    đích thì một câu UPDATE thẳng đụng khoá và MySQL chối IM — số tạm ứng ở lại tên cũ trong
 *    khi dòng chi đã sang tên mới. Đúng kiểu hỏng nửa vời mà màn nào cũng trông bình thường. */
$dung_don( 'DX2', AO, 500 );
$wpdb->insert( VHCP_DB::t( 'tamung' ), array( 'ma_don' => 'DX2', 'coso' => THAT2, 'so' => 700 ) );
$rb5 = VHCP_Don::doi_coso_don( 'DX2', THAT2, AO );
t( '🔴 đổi vào gian ĐÃ CÓ dòng tạm ứng vẫn chạy', ! empty( $rb5['success'] ), $rb5 );
teq( '🔴 hai dòng tạm ứng CỘNG DỒN làm một', '1200', (string) $wpdb->get_var( $wpdb->prepare(
	'SELECT so FROM ' . VHCP_DB::t( 'tamung' ) . ' WHERE ma_don=%s AND coso=%s', 'DX2', THAT2 ) ) );
teq( '   và không còn dòng tạm ứng nào của cơ sở ảo', 0, (int) $wpdb->get_var( $wpdb->prepare(
	'SELECT COUNT(*) FROM ' . VHCP_DB::t( 'tamung' ) . ' WHERE ma_don=%s AND coso=%s', 'DX2', AO ) ) );

/* ═══ B5. ĐƠN GHÉP NHIỀU GIAN — PHẢI CHỈ RÕ, KHÔNG ĐOÁN ════════════════════════════
 * 🔴 Đoán bừa là gom nhầm tiền của một gian không liên quan sang chỗ khác. */
$dung_don( 'DX3', AO, 300 );
$wpdb->insert( VHCP_DB::t( 'chiphi' ), array( 'id' => 'DX3_c2', 'ma_don' => 'DX3', 'coso' => THAT2, 'thanh_tien' => 400 ) );
$rb6 = VHCP_Don::doi_coso_don( 'DX3', THAT );
t( '🔴 đơn 2 gian mà không chỉ rõ đổi gian nào thì chối', empty( $rb6['success'] ), $rb6 );
$rb7 = VHCP_Don::doi_coso_don( 'DX3', THAT, AO );
t( '   chỉ rõ thì chạy', ! empty( $rb7['success'] ), $rb7 );
teq( '   và KHÔNG đụng gian kia', 1, (int) $wpdb->get_var( $wpdb->prepare(
	'SELECT COUNT(*) FROM ' . VHCP_DB::t( 'chiphi' ) . ' WHERE ma_don=%s AND coso=%s', 'DX3', THAT2 ) ) );

/* ═══ B6. ĐỔI SANG CHÍNH NÓ / ĐƠN KHÔNG CÓ ═════════════════════════════════════════ */
$rb8 = VHCP_Don::doi_coso_don( 'DX1', THAT );
t( 'đổi sang gian đang đứng thì chối', empty( $rb8['success'] ), $rb8 );
$rb9 = VHCP_Don::doi_coso_don( 'KHONG_CO_DON_NAY', THAT );
t( 'đơn không tồn tại thì chối', empty( $rb9['success'] ), $rb9 );

/* ═══ B7. CỬA API ══════════════════════════════════════════════════════════════════ */
t( "🔴 'doiCoSoDon' đã khai vào cửa API",
	false !== strpos( $src, "'doiCoSoDon'" )
	&& false !== strpos( $src, "array( 'VHCP_Don', 'doi_coso_don' )" ) );
t( '   và nhân viên bị chặn ở cổng', false !== strpos( $src, "'doiCoSoDon'," ) );

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo '  · ' . $x . "\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: gộp cơ sở ảo quét hết năm bảng, và Admin đổi được cơ sở của từng đơn.\n";
