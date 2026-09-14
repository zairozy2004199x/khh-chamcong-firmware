<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * CƠ SỞ HÚT TỪ BÊN GHẾ VỀ ĐÚNG NHÀ CỦA BẢN ẤY.
 *
 * Anh Thắng 14/09/2026: *"chi phí [máy] tự động lấy cơ sở từ ghế, còn chi phí văn phòng lấy từ
 * đó, chỉnh lại"*.
 *
 * =============================================================================================
 * 🔴 GẮN CỨNG 'POSH' LÀ ĐÚNG CHO KHU VUI CHƠI VÀ SAI CHO MỌI BẢN KHÁC.
 *
 * Bên KVC, gian ghế là của nhà POSH nên cơ sở hút về phải mang tên nhà ấy. Nhưng bản Máy tự động
 * và bản Văn phòng có nhà riêng; cơ sở hút về mà mang 'POSH' thì HỎNG IM LẶNG CẢ HAI ĐƯỜNG:
 *   · người dùng nhà mặc định của bản ấy KHÔNG NHÌN THẤY cơ sở (danh mục lọc theo đơn vị) — mở
 *     hộp chọn ra thấy trống trơn dù danh mục đầy;
 *   · và mọi báo cáo theo nhà hụt đúng phần tiền của những gian ấy.
 *
 * ⚠️ Bản mảng riêng nay KHÔNG gieo danh mục cơ sở nào (xem `kiem-gieo-dung-mot-lan.php`), nên
 *    bên Ghế là NGUỒN CƠ SỞ DUY NHẤT của chúng. Hút sai nhà = trang không dùng được.
 *
 * Chạy: php tools/test/kiem-coso-tu-ghe.php
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

/* ═══ 1. BẢN GỐC GIỮ 'POSH', BẢN VÙNG VỀ NHÀ MÌNH ════════════════════════════════ */
teq( '🔴 bản gốc khu vui chơi: cơ sở ghế thuộc POSH', 'POSH', VHCP_Cfg::don_vi_ghe() );

foreach ( array( 'mtd', 'vp' ) as $ma ) {
	$f = $GOC . '/wordpress/vhcp-chi-phi-' . $ma . '/includes/class-vhcp-cfg.php';
	if ( ! is_file( $f ) ) { continue; }
	$m = file_get_contents( $f );
	t( '🔴 bản «' . $ma . '»: hằng để RỖNG (= nhà mặc định của chính bản ấy)',
		(bool) preg_match( "#const DON_VI_GHE = '';#", $m ), '' );
	t( '⚠️ và vẫn còn cơ chế hút từ Ghế (lớp VHG_May giữ nguyên, không bị đổi tiền tố)',
		false !== strpos( $m, "class_exists( 'VHG_May' )" ), '' );
}
/* Script tách phải có CHỐT, không chỉ có lệnh thay: thay trượt thì im lặng. */
$sh = file_get_contents( $GOC . '/tools/tach-ban-vung.sh' );
t( '🔴 script tách có chốt kiểm hằng ấy',
	(bool) preg_match( '#grep -q "const DON_VI_GHE = \x27\x27;"#', $sh ), '' );

/* ═══ 1b. MẢNG NÀO CÓ GHẾ, MẢNG NÀO KHÔNG ═══════════════════════════════════════
 *
 * Anh Thắng 14/09/2026: *"VP không dùng cơ sở ghế, ghế chỉ mỗi MTD thôi"*.
 *
 * 🔴 Máy tự động CHÍNH LÀ mảng ghế massage nên danh mục gian của nó đúng bằng danh mục bên Ghế.
 *    Văn phòng thì không: gian ở đó là chỗ làm việc. Hút sang là mỗi lần bên Ghế mở thêm một
 *    điểm đặt máy, danh mục Văn phòng lại dài thêm một dòng lạ — rồi người nhập chọn nhầm, và
 *    tiền văn phòng rơi vào một gian ghế.
 * ═══════════════════════════════════════════════════════════════════════════════ */
teq( '🔴 bản gốc khu vui chơi: CÓ lấy từ Ghế', true, VHCP_Cfg::LAY_COSO_GHE );
$mtd_ma = @file_get_contents( $GOC . '/wordpress/vhcp-chi-phi-mtd/includes/class-vhcp-cfg.php' );
$vp_ma  = @file_get_contents( $GOC . '/wordpress/vhcp-chi-phi-vp/includes/class-vhcp-cfg.php' );
if ( $mtd_ma ) { t( '🔴 bản «mtd» (máy tự động): CÓ lấy từ Ghế',
	(bool) preg_match( '#const LAY_COSO_GHE = true;#', $mtd_ma ), '' ); }
if ( $vp_ma )  { t( '🔴 bản «vp» (văn phòng): KHÔNG lấy từ Ghế',
	(bool) preg_match( '#const LAY_COSO_GHE = false;#', $vp_ma ), '' ); }
t( '🔴 script tách khai đúng mảng nào tắt', (bool) preg_match( '#vp\) GHE=false#', $sh ), '' );
/* ⚠️ MẶC ĐỊNH LÀ BẬT: vùng mới sinh mà quên khai thì theo nếp bản gốc, chứ không lặng lẽ mất
   một đường dữ liệu. */
t( '⚠️ vùng chưa khai thì mặc định BẬT', (bool) preg_match( '#\*\)  GHE=true#', $sh ), '' );

/* 🔴 TẮT LÀ TẮT CẢ BỐN LỐI — lượt hút, tai nghe từ Ghế, nút bấm tay, và đầu phát ngược. Tắt ba
   để sót một thì cơ sở vẫn chảy sang, chỉ là chậm hơn và khó truy hơn. */
$cfg0 = preg_replace( '#/\*[\s\S]*?\*/|//[^\n]*#', ' ',
	file_get_contents( $GOC . '/wordpress/vhcp-chi-phi/includes/class-vhcp-cfg.php' ) );
/* ⚠️ SOI TRONG THÂN HÀM, KHÔNG ĐỌC LAN SANG HÀM KẾ BÊN. Bản nháp của phép này quét 260 ký tự
   sau tên hàm — xoá sạch gác của `moc_coso_ghe()` mà bài vẫn xanh, vì nó đọc trúng chữ
   `LAY_COSO_GHE` của `hut_coso_ghe_api()` nằm ngay dưới. Phép kiểm nói dối đúng chỗ nó phải
   canh. Nay cắt thân hàm ra trước rồi mới soi. */
function than_ham( $src, $ten ) {
	$i = strpos( $src, 'function ' . $ten . '(' );
	if ( false === $i ) { return ''; }
	$j = strpos( $src, "\n\t}", $i );
	return ( false === $j ) ? '' : substr( $src, $i, $j - $i );
}
foreach ( array(
	'hut_coso_ghe'     => 'lượt hút',
	'moc_coso_ghe'     => 'tai nghe từ Ghế',
	'hut_coso_ghe_api' => 'nút bấm tay',
	'bao_coso_posh_'   => 'đầu phát ngược',
) as $ham => $nhan ) {
	$than = than_ham( $cfg0, $ham );
	t( '🔴 ' . $nhan . ' có gác LAY_COSO_GHE trong THÂN nó',
		'' !== $than && false !== strpos( $than, 'LAY_COSO_GHE' ), $ham );
}
/* ⚠️ Và màn giấu nút đi: bày một nút bấm vào chỉ nhận câu chối là thứ người ta bấm đi bấm lại
   rồi nghĩ trang hỏng. */
$html0 = file_get_contents( $GOC . '/wordpress/vhcp-chi-phi/templates/app.html' );
t( '⚠️ màn giấu nút khi mảng không dùng ghế', false !== strpos( $html0, 'function anNutHutGhe(' ), '' );
t( 'và nó chạy mỗi lượt dựng màn Cấu hình',
	(bool) preg_match( '#function renderCfg\(\)[\s\S]{0,400}?anNutHutGhe\(\);#', $html0 ), '' );
t( '🔴 máy chủ gửi cờ xuống màn',
	false !== strpos( file_get_contents( $GOC . '/wordpress/vhcp-chi-phi/includes/class-vhcp-app.php' ),
		"'layCoSoGhe' => VHCP_Cfg::LAY_COSO_GHE" ), '' );

/* ═══ 2. RỖNG PHẢI RA NHÀ MẶC ĐỊNH, KHÔNG PHẢI "KHÔNG CÓ NHÀ" ════════════════════ */
teq( '🔴 chuan("") = nhà mặc định', VHCP_DonVi::MAC_DINH, VHCP_DonVi::chuan( '' ) );

/* ═══ 3. MỌI LỐI ĐỀU HỎI MỘT CỬA `don_vi_ghe()` ══════════════════════════════════
 * 🔴 Ba chỗ dùng con số này: lượt hút, tai nghe móc từ Ghế, và đầu phát ngược lại. Chỗ nào còn
 *    đọc thẳng hằng thì bản vùng vẫn hỏng ở đúng chỗ ấy — mà hỏng lẻ một đường thì khó thấy hơn
 *    hỏng cả ba.
 * ═══════════════════════════════════════════════════════════════════════════════ */
$cfg = preg_replace( '#/\*[\s\S]*?\*/#', ' ',
	file_get_contents( $GOC . '/wordpress/vhcp-chi-phi/includes/class-vhcp-cfg.php' ) );
t( '🔴 lượt hút hỏi don_vi_ghe()',
	(bool) preg_match( '#nhan_coso_ngoai\( \$ten, self::don_vi_ghe\(\) \) \) \{ \$n\+\+; \}#', $cfg ), '' );
t( '🔴 tai nghe từ Ghế cũng vậy',
	(bool) preg_match( '#moc_coso_ghe[\s\S]{0,200}?self::don_vi_ghe\(\)#', $cfg ), '' );
/* ⚠️ Đầu phát ngược (chi phí -> ghế) so với ĐƠN VỊ ĐANG DÙNG. So với hằng thì bản vùng im hẳn,
   và bên Ghế không bao giờ biết cơ sở vừa được khai. */
t( '⚠️ đầu phát ngược so với đơn vị ĐANG DÙNG, không so hằng',
	(bool) preg_match( '#VHCP_DonVi::chuan\( self::don_vi_ghe\(\) \) !== \$dv#', $cfg ), '' );
t( '🔴 không còn chỗ nào đọc thẳng self::DON_VI_GHE ngoài chính hàm ấy',
	1 === preg_match_all( '#self::DON_VI_GHE#', $cfg ), '' );

/* ═══ 4. CHẠY THẬT ═══════════════════════════════════════════════════════════════ */
update_option( 'vhcp_dv_ghe', 'NHÀ THỬ' );
teq( 'khai lại bằng khoá cấu hình thì theo khoá', 'NHÀ THỬ', VHCP_Cfg::don_vi_ghe() );
update_option( 'vhcp_dv_ghe', '' );
teq( '⚠️ khoá để rỗng = rỗng thật (nhà mặc định), không lui về POSH', '', VHCP_Cfg::don_vi_ghe() );
delete_option( 'vhcp_dv_ghe' );
teq( 'bỏ khoá thì lui về hằng của bản', 'POSH', VHCP_Cfg::don_vi_ghe() );

/* ═══════════════════════════════════════════════════════════════════════════════════ */
if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo "  · $x\n"; }
	exit( 1 );
}
echo "\n✓ ĐẠT: $DAT phép thử — cơ sở hút từ Ghế về đúng nhà của từng bản.\n";
