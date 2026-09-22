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
function VHCp_ds_tam() { return VHCP_Cfg::dau_muc_ds(); }
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

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 3b. 🔴 BẢNG MẶC ĐỊNH PHẢI LÀ SƠ ĐỒ ANH THẮNG VẼ TAY (22/09/2026), KHÔNG PHẢI BẢNG EM BỊA
 * ══════════════════════════════════════════════════════════════════════════════════════════════
 * Mấy phép trên mới chỉ đòi "8–12 mục và có ô Khác" — bảng nào cũng qua được, kể cả bảng cũ
 * chia theo MÓN mà kế toán nhìn vào không thấy lối nào khớp. Khối này buộc bảng vào đúng ba
 * gốc trên giấy: CP Chung · CP Cơ sở · CP Tiền thuê.
 *
 * ⚠️ PHÉP "BA GỐC LIỀN KHỐI" KHÔNG PHẢI PHÉP LÀM ĐẸP. `_optsHtml()` xếp `<optgroup>` theo ĐÚNG
 *    thứ tự bảng này, mà `<optgroup>` chỉ có MỘT tầng — tầng một nằm ở tiền tố. Xen một mục
 *    "Cơ sở ·" vào giữa khối "Chung ·" là ô chọn hiện ra hai cụm "Chung" rời nhau, tức mất
 *    luôn cái tầng mà tiền tố đang gánh. Trình duyệt không báo gì; chỉ người nhập thấy rối.
 */
$dm  = VHCP_Cfg::DAU_MUC_MAC_DINH;
$goc3 = array( 'Chung · ', 'Cơ sở · ', 'Tiền thuê · ' );

teq( '🔴 ô hứng "Khác" đứng CUỐI, không lẫn vào giữa ba gốc', 'Khác', end( $dm ) );

$khong_goc = array();
foreach ( array_slice( $dm, 0, -1 ) as $x ) {
	$co = false;
	foreach ( $goc3 as $g ) { if ( 0 === strpos( $x, $g ) ) { $co = true; break; } }
	if ( ! $co ) { $khong_goc[] = $x; }
}
t( '🔴 mọi mục (trừ "Khác") thuộc đúng MỘT trong ba gốc của sơ đồ', array() === $khong_goc, $khong_goc );

/* Ba gốc phải nằm liền khối — xem chú thích trên. Lấy dãy tiền tố rồi bỏ chỗ lặp liền nhau:
   liền khối thì còn đúng ba, xen kẽ thì còn nhiều hơn. */
$day = array();
foreach ( array_slice( $dm, 0, -1 ) as $x ) {
	foreach ( $goc3 as $g ) { if ( 0 === strpos( $x, $g ) ) { $day[] = $g; break; } }
}
$rut = array();
foreach ( $day as $g ) { if ( ! $rut || end( $rut ) !== $g ) { $rut[] = $g; } }
teq( '🔴 ba gốc nằm LIỀN KHỐI, không xen kẽ nhau', $goc3, $rut );

/* ĐỦ MƯỜI MỘT NHÁNH CỦA SƠ ĐỒ. Kê từng cái ra chứ không đếm đầu mục: đếm thì bỏ mất một
   nhánh rồi thêm bừa một nhánh khác vẫn qua, mà mất "Tiền thuê · Mall" là tiền thuê mặt bằng
   — khoản cố định to nhất — rơi hết vào ô "Khác".
   Danh sách này là SÀN, không phải trần: thêm nhánh mới thì cứ thêm, chỉ đừng bỏ nhánh cũ đi
   trong im lặng. Trục của nhánh Cơ sở là AI MUA, không phải MUA CÁI GÌ. */
$phai_co = array(
	'Chung · Văn phòng', 'Chung · Vận hành & Cơ sở',
	'Cơ sở · Marketing mua', 'Cơ sở · Vận hành mua', 'Cơ sở · Kỹ thuật mua',
	'Cơ sở · Cơ sở tự mua', 'Cơ sở · Nguyên vật liệu', 'Cơ sở · Hàng hoá nhập kho',
	'Cơ sở · Phụ cấp nhân viên',
	'Tiền thuê · Mall', 'Tiền thuê · Điện, nước, phụ phí',
);
foreach ( $phai_co as $nhanh ) {
	t( "🔴 còn đủ nhánh của sơ đồ — \"$nhanh\"", in_array( $nhanh, $dm, true ), $dm );
}

/* Ba thứ trong sơ đồ CỐ Ý không nằm ở đây: phép phân bổ (bổ 50/50, bổ theo DT Gian), trường
   riêng trên đơn (VAT / set-up hay vận hành), và phân quyền người đề xuất. Nhét chúng vào
   danh mục là lẫn trục — đúng cái bẫy mà cả bản này sinh ra để tránh. */
foreach ( array( '50%', 'VAT', 'set up', 'Set up', 'đề xuất' ) as $lac ) {
	t( "🔴 không mục nào lẫn trục khác vào danh mục — không thấy \"$lac\"",
		false === strpos( implode( '|', $dm ), $lac ), $dm );
}

update_option( 'vhcp_dau_muc_ds', "Một\nHai\n\n  Ba  \nHai" );
$ds2 = VHCP_Cfg::dau_muc_ds();
teq( '🔴 khai tay thì THẮNG mặc định, và tự dọn dòng rỗng / trùng / thừa dấu cách',
	array( 'Một', 'Hai', 'Ba' ), $ds2 );
/* 🔴 TÊN CHỨA DẤU NGĂN. Một dòng loại chi phí giữ nhiều đầu mục ngăn bằng `|`, nên một cái TÊN
   chứa `|` là lúc đọc ngược nó tự vỡ làm hai đầu mục ma — mà kế toán gõ tên tay, không ai cấm
   họ gõ dấu ấy. Phải tước ngay lúc nhận, không phải lúc dùng. */
update_option( 'vhcp_dau_muc_ds', "Điện | nước\nCơ sở · Tự mua" );
teq( '🔴 tên chứa dấu ngăn `|` bị tước ngay lúc nhận, không để tự vỡ thành đầu mục ma',
	array( 'Điện / nước', 'Cơ sở · Tự mua' ), VHCP_Cfg::dau_muc_ds() );

/* ⚠️ Đổi thành `/` chứ không BỎ ĐI: bỏ đi thì "A|B" thành "AB", đọc ra một tên khác hẳn. */
update_option( 'vhcp_dau_muc_ds', "A|B" );
teq( '   và đổi thành dấu `/`, không dính liền thành một chữ khác',
	array( 'A/B' ), VHCp_ds_tam() );
delete_option( 'vhcp_dau_muc_ds' );

/* ── Tách chuỗi nhiều đầu mục của MỘT dòng ────────────────────────────────────────────────── */
teq( '🔴 một dòng nhiều đầu mục -> tách đủ', array( 'Cơ sở · Tự mua', 'Cơ sở · NVL' ),
	VHCP_Cfg::dau_muc_tach( 'Cơ sở · Tự mua|Cơ sở · NVL' ) );
teq( '   dọn dòng rỗng, khoảng trắng thừa và cái trùng',
	array( 'A', 'B' ), VHCP_Cfg::dau_muc_tach( ' A ||B|A|  ' ) );
teq( '   rỗng vào thì mảng rỗng ra — chỗ gọi tự quyết dồn vào ô hứng hay không',
	array(), VHCP_Cfg::dau_muc_tach( '' ) );
teq( '🔴 một đầu mục vẫn chạy y như trước bản này', array( 'Khác' ),
	VHCP_Cfg::dau_muc_tach( 'Khác' ) );

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
	/* Ô khai đầu mục: tích được nhiều, và mốc bám là `data-dm-nhieu` — cùng nếp `data-vai`,
	   `data-dv-nhieu` của hai cột bên cạnh. */
	$osel = than_ham( $h, '_dauMucSel' );
	t( "🔴 $ban: ô khai đầu mục TÍCH ĐƯỢC NHIỀU, không còn chọn một",
		false !== strpos( $osel, 'type="checkbox"' ) && false !== strpos( $osel, 'data-dm-nhieu' ), $osel );
	t( "$ban: và vẫn bày lại đầu mục cũ không còn trong danh sách",
		false !== strpos( $osel, 'ds.indexOf(k)<0' ), $osel );

	$luu = than_ham( $h, 'saveCfgTkNoMx' );
	/* ⚠️ ĐỪNG GHIM NGUYÊN VĂN MỘT LỐI VIẾT. Phép này từng soi chuỗi `oDm ? String(` — đúng
	   hình dạng hồi ô còn là `<select>`. Đổi sang ô tích là phép ấy đỏ, mà đỏ vì LỐI VIẾT
	   đổi chứ không phải vì hành vi hỏng. Soi hai thứ thật sự cần: có hỏi ô còn đó không,
	   và khi không có thì có giữ lại giá trị cũ không. */
	t( "🔴 $ban: lưu chỉ ghi đầu mục KHI Ô CÓ MẶT",
		false !== strpos( $luu, 'oDm ?' ) && false !== strpos( $luu, 'goc.dauMuc' ), $luu );
	t( "🔴 $ban: lưu gom NHIỀU ô tích, ngăn bằng `|` chứ không phải dấu phẩy",
		false !== strpos( $luu, "join('|')" ) && false === strpos( $luu, "join(',')" ), $luu );
	/* 🔴 HAI ĐẦU PHẢI BÁM CÙNG MỘT MỐC. Ô do `_dauMucSel()` dựng, chỗ Lưu đi tìm lại bằng
	   `querySelector`. Đổi tên mốc ở một đầu thôi là chỗ Lưu không thấy ô nữa, rơi vào nhánh
	   "giữ nguyên giá trị cũ" — tức kế toán tích xong bấm Lưu mà KHÔNG GÌ được ghi, im lặng.
	   Phép grep suông không bắt được ca ấy; phải so hai đầu với nhau. */
	if ( preg_match( '/querySelector\(\s*\x27\[([a-z0-9-]+)\]\x27\s*\)/', $luu, $mm ) ) {
		t( "🔴 $ban: chỗ Lưu tìm ĐÚNG mốc mà ô khai đầu mục dựng ra (`{$mm[1]}`)",
			false !== strpos( $osel, $mm[1] ), $mm[1] );
	} else {
		t( "$ban: đọc được mốc chỗ Lưu đi tìm", false, substr( $luu, 0, 400 ) );
	}

	/* 🔴 DẢI NÚT Ở MÀN NHẬP ĐƠN — đúng chỗ anh Thắng chỉ: *"trong bảng nhập nó chỉ hiện loại
	   chi phí chứ không phải đầu mục, đang ngược"*. Dải ấy gom theo cột Bộ phận đã gỡ, nên tự
	   ẩn. Hành vi đầy đủ do `kiem-dai-nut-dau-muc.js` canh; ở đây chỉ đòi CẢ BỐN BẢN đều có,
	   vì bản vùng sinh lại từ gốc và sót một bản là mất tính năng không ai biết. */
	$nhom = than_ham( $h, '_cacNhomCp' );
	t( "🔴 $ban: dải nút nhập đơn gom theo ĐẦU MỤC",
		false !== strpos( $nhom, '_dauMucDangDung()' ) && false !== strpos( $nhom, '_dauMucCua(x)' ), $nhom );
	t( "$ban: chưa khai đầu mục thì vẫn gom theo bộ phận như cũ",
		false !== strpos( $nhom, '_khoaNhom(' ), $nhom );
	$hop = than_ham( $h, '_hopNhomCp' );
	t( "🔴 $ban: bấm nút đầu mục thì ô chọn LỌC theo đúng nút ấy",
		false !== strpos( $hop, '_dauMucCua(x).indexOf(NHOM_CP)' ), $hop );
}

// ============================================================ kết
if ( $truot ) {
	echo "HỎNG: " . count( $truot ) . "\n";
	foreach ( $truot as $x ) { echo "  ✗ $x\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — đầu mục gom được, và cờ lọc theo vai đúng ở cả bốn bản.\n";
