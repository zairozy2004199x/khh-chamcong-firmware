<?php
/**
 * ĐẦU MỤC CHI PHÍ — GOM, KHÔNG LỌC
 * =============================================================================================
 *
 * Anh Thắng 22/09/2026: *"phân loại để lên chi phí dễ nhất, các bộ phận nhập được"*, *"phân
 * theo đầu mục chi phí lớn"*, rồi chốt *"làm luôn cho wed hà nội"*.
 *
 * 🔴 CHỖ HỎNG CỦA BẢN CŨ, ĐO ĐƯỢC: một dòng Loại chi phí mang BỐN cột LỌC (Bộ phận đã chết ·
 *    Đơn vị · Khối · Vai trò) và KHÔNG MỘT CỘT NÀO ĐỂ GOM. Sai vai là không thấy ô của mình.
 *    Hai hệ mã nguồn mở lớn nhất làm ngược lại — đo trên chính mã của họ:
 *      · ERPNext `Expense Claim Type`: bốn trường, KHÔNG trường nào hạn chế vai/bộ phận;
 *      · Odoo: sáu danh mục, phẳng, mọi người thấy hết.
 *
 * 🔴 VÀ ĐÂY LÀ BÀI KIỂM CỦA MỘT CỜ THEO VÙNG, nên nó phải soi CẢ BỐN BẢN:
 *      · Khu vui chơi · MTĐ · VP  -> BẬT lọc (hành vi cũ, không đổi một ly)
 *      · Hà Nội                    -> TẮT lọc (mọi bộ phận nhập được)
 *    Bản vùng được SINH LẠI từ bản gốc, nên sót cờ ở một bản là lượt sinh sau mất tính năng
 *    mà không ai biết — đúng lý do bài này tồn tại.
 *
 * Chạy: php tools/test/kiem-dau-muc-chi-phi.php
 */

require_once __DIR__ . '/wp-stub.php';
$goc = dirname( dirname( __DIR__ ) );
if ( ! is_dir( $goc . '/wordpress' ) ) { $goc = dirname( __DIR__, 2 ); }

$dat = 0; $truot = array();
function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( $them === null ? '' : ( "\n      → " . ( is_scalar( $them ) ? var_export( $them, true ) : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) ) );
}
function teq( $ten, $mong, $thuc ) {
	t( $ten . ' (mong ' . json_encode( $mong, JSON_UNESCAPED_UNICODE ) . ')', $mong === $thuc, $thuc );
}

/**
 * TƯỚC CHÚ THÍCH TRƯỚC KHI SOI.
 *
 * 🔴 Bài này đã xanh vì lý do SAI một lần: phép "có gom bằng optgroup" khớp phải ô chọn MÃ TÀI
 *    KHOẢN vốn đã dùng `<optgroup>` từ lâu, nên gỡ sạch phép gom mới mà bài vẫn xanh. Và mấy
 *    phép bên máy chủ thì khớp phải CHÍNH CHÚ THÍCH nhắc tên hàm vừa bị gỡ.
 *    Soi chuỗi trên cả tệp là soi vào một cái kho chữ, không phải vào mã đang chạy.
 */
function chi_ma( $h ) {
	$h = preg_replace( '#<!--[\s\S]*?-->#', ' ', $h );
	$h = preg_replace( '#/\*[\s\S]*?\*/#', ' ', $h );
	return preg_replace( '#(^|[^:])//[^\n]*#m', '$1', $h );
}

/**
 * Bóc THÂN một hàm ra để soi — thay vì soi cả tệp.
 *
 * ⚠️ Cắt tới dấu đóng ngoặc cùng mức thụt đầu dòng. Thô, nhưng đủ cho mã của bộ này (mọi hàm
 *    đều thụt hai dấu cách bên màn, một dấu tab bên PHP) và không kéo thêm thư viện nào vào
 *    một bài kiểm.
 */
function than_ham( $s, $ten, $thut = '  ' ) {
	$i = strpos( $s, $thut . 'function ' . $ten . '(' );
	if ( false === $i ) { return ''; }
	$j = strpos( $s, "\n" . $thut . '}', $i );
	return ( false === $j ) ? '' : substr( $s, $i, $j - $i );
}

$BAN = array(
	'vhcp-chi-phi'     => array( 'VHCP',    true  ),
	'vhcp-chi-phi-hn'  => array( 'VHCPHN',  false ),
	'vhcp-chi-phi-mtd' => array( 'VHCPMTD', true  ),
	'vhcp-chi-phi-vp'  => array( 'VHCPVP',  true  ),
);

// ============================================================ 1. 🔴 CỜ ĐÚNG Ở CẢ BỐN BẢN
foreach ( $BAN as $ban => $x ) {
	$f = $goc . '/wordpress/' . $ban . '/includes/class-vhcp-cfg.php';
	t( "có $ban", is_file( $f ) );
	if ( ! is_file( $f ) ) { continue; }
	$s = file_get_contents( $f );
	$mong = $x[1] ? 'true' : 'false';
	t( "🔴 $ban: LOC_LOAI_THEO_VAI = $mong", false !== strpos( $s, 'const LOC_LOAI_THEO_VAI = ' . $mong . ';' ),
		( preg_match( '/const LOC_LOAI_THEO_VAI = (\w+);/', $s, $m ) ? $m[1] : 'KHÔNG CÓ' ) );
	/* Danh sách đầu mục phải có ở MỌI bản — kể cả bản còn bật lọc. Cột gom là thứ dùng được
	   cho cả hai chế độ; chỉ bản nào bật cờ mới thôi lọc. */
	t( "$ban: có bảng đầu mục mặc định", false !== strpos( $s, 'DAU_MUC_MAC_DINH' ) );
	t( "$ban: có hàm đọc danh sách đầu mục", false !== strpos( $s, 'function dau_muc_ds()' ) );
}

// ============================================================ 2. HÀNH VI CỦA CỜ
require_once $goc . '/wordpress/vhcp-chi-phi/includes/class-vhcp-util.php';
require_once $goc . '/wordpress/vhcp-chi-phi/includes/class-vhcp-db.php';
require_once $goc . '/wordpress/vhcp-chi-phi/includes/class-vhcp-cfg.php';

teq( '🔴 bản gốc mặc định VẪN LỌC theo vai — khu vui chơi không đổi một ly',
	true, VHCP_Cfg::LOC_LOAI_THEO_VAI );
teq( 'chưa khai ô cấu hình thì theo hằng', true, VHCP_Cfg::loc_loai_theo_vai() );

/* Ô cấu hình THẮNG hằng — để một site đổi được mà không phải dựng bản mới. */
update_option( 'vhcp_loc_loai_theo_vai', '0' );
teq( '🔴 khai ô cấu hình = 0 thì TẮT lọc', false, VHCP_Cfg::loc_loai_theo_vai() );
update_option( 'vhcp_loc_loai_theo_vai', '1' );
teq( 'khai = 1 thì bật lại', true, VHCP_Cfg::loc_loai_theo_vai() );
delete_option( 'vhcp_loc_loai_theo_vai' );
teq( '🔴 xoá ô đi thì VỀ HẰNG, không rơi về tắt', true, VHCP_Cfg::loc_loai_theo_vai() );

// ============================================================ 3. DANH SÁCH ĐẦU MỤC
$ds = VHCP_Cfg::dau_muc_ds();
t( '🔴 chưa khai gì vẫn CÓ danh sách để chọn (rơi về mặc định)', count( $ds ) > 0, $ds );
$n = count( $ds );
t( '🔴 số đầu mục nằm trong khoảng 8–12 — trên 12 là chọn bừa, dưới 8 là không có ô đúng',
	$n >= 8 && $n <= 12, $n );
teq( 'không có đầu mục trùng nhau', $n, count( array_unique( $ds ) ) );
t( '🔴 có ô hứng "Khác" — thiếu nó là người ta nhét bừa vào ô gần giống',
	in_array( 'Khác', $ds, true ), $ds );

update_option( 'vhcp_dau_muc_ds', "Một\nHai\n\n  Ba  \nHai" );
$ds2 = VHCP_Cfg::dau_muc_ds();
teq( '🔴 khai tay thì THẮNG mặc định, và tự dọn dòng rỗng / trùng / thừa dấu cách',
	array( 'Một', 'Hai', 'Ba' ), $ds2 );
update_option( 'vhcp_dau_muc_ds', "\n\n  \n" );
teq( '🔴 khai toàn dòng rỗng thì VẪN rơi về mặc định — ô chọn trống là người nhập kẹt cứng',
	$ds, VHCP_Cfg::dau_muc_ds() );
delete_option( 'vhcp_dau_muc_ds' );

// ============================================================ 4. 🔴 CHỐT Ở MÁY CHỦ
require_once $goc . '/wordpress/vhcp-chi-phi/includes/class-vhcp-auth.php';
$r = new ReflectionClass( 'VHCP_Auth' );
$m = $r->getMethod( 'xem_duoc_loai' );
$than = chi_ma( implode( '', array_slice( file( $m->getFileName() ),
	$m->getStartLine() - 1, $m->getEndLine() - $m->getStartLine() + 1 ) ) );
t( '🔴 chốt máy chủ HỎI CỜ, không đọc hằng thẳng',
	false !== strpos( $than, 'loc_loai_theo_vai()' ), $than );
$i_co = strpos( $than, 'loc_loai_theo_vai()' );
$i_loc = strpos( $than, 'loai_thuoc_vai(' );
t( '🔴 và hỏi TRƯỚC khi lọc — hỏi sau thì cờ chẳng tắt được gì',
	false !== $i_co && false !== $i_loc && $i_co < $i_loc, array( $i_co, $i_loc ) );

// ============================================================ 5. 🔴 GÓI KHỞI ĐỘNG PHẢI CHỞ ĐỦ
/*
 * Ô chọn lúc NHẬP ĐƠN dựng từ gói khởi động, không phải gói Cấu hình. Thiếu một khoá ở đây
 * trông y hệt một danh sách rỗng — đúng ca `boPhanDs` đã mắc ngày 21/09/2026.
 */
$don = file_get_contents( $goc . '/wordpress/vhcp-chi-phi/includes/class-vhcp-don.php' );
t( "🔴 gói khởi động chở danh sách đầu mục", false !== strpos( $don, "'dauMucDs'" ), null );
t( "🔴 gói khởi động chở CỜ lọc theo vai", false !== strpos( $don, "'locLoaiTheoVai'" ), null );
t( "🔴 và mỗi loại chi phí chở theo đầu mục của nó",
	false !== strpos( $don, "'dauMuc'" ), null );

$cfg = file_get_contents( $goc . '/wordpress/vhcp-chi-phi/includes/class-vhcp-cfg.php' );
t( '🔴 cột đầu mục ĐƯỢC GHI XUỐNG SỔ (không thì lưu xong mất)',
	false !== strpos( $cfg, "\$g( \$x, 'dauMuc' )" ), null );
t( '🔴 và ĐỌC LÊN TỪ SỔ ở đúng cột 12',
	false !== strpos( $cfg, "'dauMuc' => isset( \$r[11] ) ? \$r[11] : ''" ), null );

// ============================================================ 6. 🔴 BÊN MÀN
foreach ( array_keys( $BAN ) as $ban ) {
	$f = $goc . '/wordpress/' . $ban . '/templates/app.html';
	if ( ! is_file( $f ) ) { t( "có $ban/app.html", false ); continue; }
	$h = chi_ma( file_get_contents( $f ) );

	/* ⚠️ SOI THÂN `_optsHtml()`, KHÔNG SOI CẢ TỆP. Ô chọn MÃ TÀI KHOẢN đã dùng `<optgroup>` từ
	   lâu (hai dòng ở `_tkSelHtml`), nên soi cả tệp là gỡ sạch phép gom mới mà bài vẫn xanh —
	   đã mắc đúng thế lúc viết bài này. */
	$opts = than_ham( $h, '_optsHtml' );
	t( "$ban: bóc được _optsHtml()", strlen( $opts ) > 200, strlen( $opts ) );
	t( "🔴 $ban: ô chọn LOẠI CHI PHÍ gom bằng optgroup",
		false !== strpos( $opts, '<optgroup label=' ), $opts );
	t( "$ban: xếp nhóm theo thứ tự máy chủ đưa xuống",
		false !== strpos( $opts, 'BOOT.dauMucDs' ), null );

	$dm = than_ham( $h, '_dauMucCua' );
	t( "🔴 $ban: loại chưa khai đầu mục vẫn hiện ở nhóm cuối",
		false !== strpos( $dm, 'Chưa xếp đầu mục' ), $dm );

	$row = than_ham( $h, '_mxRowHtml' );
	t( "🔴 $ban: bảng Cấu hình CÓ ô khai đầu mục", false !== strpos( $row, '_dauMucSel(' ), $row );
	t( "$ban: và có hàm dựng ô ấy", '' !== than_ham( $h, '_dauMucSel' ) );

	$vai = than_ham( $h, '_vaiDungDuocLoai' );
	t( "🔴 $ban: màn HỎI CỜ, không tự suy",
		false !== strpos( $vai, 'BOOT.locLoaiTheoVai===false' ), $vai );
	/* ⚠️ Gói khởi động của bản CŨ không có khoá này. Thiếu khoá phải rơi về hành vi CŨ (có
	   lọc), không phải im lặng mở toang danh mục — nên phép so phải là `===false`. */
	t( "🔴 $ban: thiếu cờ thì rơi về CÓ LỌC (so `===false`, không dùng `!`)",
		false === strpos( $vai, '!BOOT.locLoaiTheoVai' ), $vai );

	/* 🔴 HÀNG VẼ BỞI BẢN CŨ KHÔNG CÓ Ô ĐẦU MỤC. Đọc ra rỗng rồi ghi đè là một lượt Lưu xoá
	   sạch công gán của kế toán — im lặng, vì bảng lưu xong vẽ lại trông vẫn bình thường. */
	$luu = than_ham( $h, 'saveCfgTkNoMx' );
	t( "🔴 $ban: lưu chỉ ghi đầu mục KHI Ô CÓ MẶT",
		false !== strpos( $luu, 'oDm ? String(' ) && false !== strpos( $luu, "goc.dauMuc" ), $luu );
}

// ============================================================ kết
if ( $truot ) {
	echo "HỎNG: " . count( $truot ) . "\n";
	foreach ( $truot as $x ) { echo "  ✗ $x\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — đầu mục gom được, và cờ lọc theo vai đúng ở cả bốn bản.\n";
