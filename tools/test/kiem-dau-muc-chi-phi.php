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
/* ⚠️ KHÔNG CÒN LUẬT "8–12 ĐẦU MỤC". Con số ấy đúng cho một danh sách PHẲNG mà người ta phải
   chọn một; ở đây danh mục CHA chỉ có ba gốc, và ba là đúng — ép cho đủ tám là dựng thêm tầng
   giả. Chỉ còn đòi: đừng ít tới mức không có ô mà chọn, đừng nhiều tới mức thành danh sách con. */
t( '🔴 danh mục cha gọn — vài gốc thôi, không phải một danh sách dài để dò',
	$n >= 2 && $n <= 8, $n );
teq( 'không có đầu mục trùng nhau', $n, count( array_unique( $ds ) ) );
t( '🔴 có ô hứng "Khác" — thiếu nó là người ta nhét bừa vào ô gần giống',
	in_array( 'Khác', $ds, true ), $ds );

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 3b. 🔴 ĐẦU MỤC LÀ DANH MỤC CHA — BA GỐC CỦA SƠ ĐỒ, KHÔNG PHẢI CÁC NHÁNH CON
 * ══════════════════════════════════════════════════════════════════════════════════════════════
 * Anh Thắng 22/09/2026: *"Sai cơ bản với nhau rồi. Loại chi phí là chi phí chi tiết, còn đầu
 * mục là Danh mục chính của chi phí"*, kèm hai ví dụ chốt lại:
 *
 *      Chi phí NVL đồ ăn  ->  Chi phí cơ sở
 *      Chi phí cơ sở      ->  Chi phí cơ sở
 *
 * 🔴 HAI VÍ DỤ ẤY LÀ PHÉP THỬ THẬT SỰ CỦA KHỐI NÀY. Bản 1.259–1.260 để bảng mặc định là MƯỜI
 *    HAI mục — trải phẳng cả ba gốc LẪN các nhánh con vào một danh sách. Với bảng ấy thì "NVL
 *    đồ ăn" rơi vào "Cơ sở · Nguyên vật liệu" còn "Chi phí cơ sở" rơi vào "Cơ sở · Cơ sở tự
 *    mua" — HAI đầu mục khác nhau, trái hẳn điều anh nói. Nên khối này đo đúng chuyện đó: hai
 *    dòng ví dụ phải rơi vào CÙNG MỘT đầu mục.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$dm = VHCP_Cfg::DAU_MUC_MAC_DINH;

teq( '🔴 ô hứng "Khác" đứng CUỐI, không lẫn vào giữa các gốc', 'Khác', end( $dm ) );

/* Ba gốc của sơ đồ, đủ cả ba — thiếu một là cả một nhánh chi phí không có chỗ đứng. */
foreach ( array( 'Chi phí chung', 'Chi phí cơ sở', 'Chi phí tiền thuê' ) as $g ) {
	t( "🔴 còn đủ gốc của sơ đồ — \"$g\"", in_array( $g, $dm, true ), $dm );
}

/* 🔴 PHÉP CHỐT: hai ví dụ của anh phải về cùng một đầu mục. Bảng nào trải phẳng nhánh con ra
   (kiểu "Cơ sở · Nguyên vật liệu" cạnh "Cơ sở · Cơ sở tự mua") là hai dòng này tách đôi ngay. */
$hop = function ( $ten ) use ( $dm ) {
	$ra = array();
	foreach ( $dm as $d ) {
		if ( false !== mb_stripos( $ten, $d ) || false !== mb_stripos( $d, $ten ) ) { $ra[] = $d; }
	}
	return $ra;
};
teq( '🔴 "Chi phí cơ sở" về đúng một đầu mục, và là "Chi phí cơ sở"',
	array( 'Chi phí cơ sở' ), $hop( 'Chi phí cơ sở' ) );
t( '🔴 bảng KHÔNG trải phẳng nhánh con ra cạnh gốc — không mục nào mang dấu "·" của tầng hai',
	! preg_grep( '/ · /u', $dm ), $dm );

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
update_option( 'vhcp_dau_muc_ds', "\n\n  \n" );
teq( '🔴 khai toàn dòng rỗng thì VẪN rơi về mặc định — ô chọn trống là người nhập kẹt cứng',
	$ds, VHCP_Cfg::dau_muc_ds() );
delete_option( 'vhcp_dau_muc_ds' );

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 3c. 🔴 CỘT `cha` PHẢI ĐI HẾT ĐƯỜNG: sổ -> máy chủ -> gói khởi động -> màn
 * ══════════════════════════════════════════════════════════════════════════════════════════════
 * Đứt một chặng nào cũng ra cùng một cảnh: kế toán bấm ＋, gõ tên, bấm Lưu, bảng vẽ lại trông
 * bình thường — rồi hôm sau mở ra thì dòng con thành một loại rời, hết là con của ai. Hỏng im
 * lặng, và chỉ lộ ra sau khi đã khai cả trăm dòng.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$cfg_src = file_get_contents( $goc . '/wordpress/vhcp-chi-phi/includes/class-vhcp-cfg.php' );
$don_src = file_get_contents( $goc . '/wordpress/vhcp-chi-phi/includes/class-vhcp-don.php' );
t( '🔴 máy chủ ĐỌC cột cha từ sổ danh mục',
	false !== strpos( $cfg_src, "'cha' => isset( \$r[12] ) ? \$r[12] : ''" ), null );
t( '🔴 máy chủ GHI cột cha xuống sổ — thiếu là lưu xong mất sạch',
	false !== strpos( $cfg_src, "\$g( \$x, 'cha' )" ), null );
t( '🔴 gói khởi động CHỞ `cha` xuống màn — thiếu là màn không biết con là con',
	false !== strpos( $don_src, "'cha' => isset( \$x['cha'] ) ? \$x['cha'] : ''" ), null );
/* ⚠️ KHÔNG gọi `loai_tk()` ở đây: nó đi xuống đường đọc sổ thật (`VHCP_Meta`), mà bệ đỡ của
   bài này cố ý không dựng cả cái đó. Khoá `cha` trong `loai_tk()` do phép grep ngay trên
   canh — cùng một dòng mã. */
t( '   và `loai_tk()` cũng khai khoá `cha` cho mọi chỗ đọc một loại lẻ',
	false !== strpos( $cfg_src, "'cha'     => isset( \$x['cha'] )" ), null );

/* Cờ theo vùng: bật ở bản gốc và MTĐ/VP (khối = mảng kinh doanh), tắt ở HN (khối = miền). */
$KHOI_MONG = array( 'vhcp-chi-phi' => 'true', 'vhcp-chi-phi-hn' => 'false',
                    'vhcp-chi-phi-mtd' => 'true', 'vhcp-chi-phi-vp' => 'true' );
foreach ( $KHOI_MONG as $ban => $mong ) {
	$f = $goc . '/wordpress/' . $ban . '/includes/class-vhcp-cfg.php';
	if ( ! is_file( $f ) ) { t( "có $ban/class-vhcp-cfg.php", false ); continue; }
	$src = file_get_contents( $f );
	t( "🔴 $ban: cờ LOC_LOAI_THEO_KHOI = $mong",
		false !== strpos( $src, "const LOC_LOAI_THEO_KHOI = $mong;" ),
		( preg_match( '/const LOC_LOAI_THEO_KHOI = (\w+);/', $src, $mk ) ? $mk[1] : '(không thấy)' ) );
	t( "   $ban: gói khởi động chở cờ ấy xuống màn",
		false !== strpos( file_get_contents( $goc . '/wordpress/' . $ban . '/includes/class-vhcp-don.php' ),
			"'locLoaiTheoKhoi' =>" ), null );
}

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

	/* ══════════════════════════════════════════════════════════════════════════════════════
	 * 🔴 KHỐI THÔI LÀ CỔNG CỦA DANH MỤC — anh Thắng 22/09/2026: *"Khối là dùng chung, vì đã
	 *    phân theo vai trò rồi, Khối là liên quan Miền Bắc và Miền Nam thôi"*.
	 * ══════════════════════════════════════════════════════════════════════════════════════
	 * Cắn thật: bản Hà Nội mở ra, ô Loại chi phí RỖNG, kèm "Khối HN chưa có loại chi phí nào
	 * (danh mục đang có 27 loại, nhưng của khối khác)". Hai bảy loại nằm đó mà không dùng
	 * được cái nào.
	 * ⚠️ Cờ đặt ở BẢN GỐC, bản vùng sinh lại — nên phải soi đủ bốn bản, như `LOC_LOAI_THEO_VAI`.
	 * ══════════════════════════════════════════════════════════════════════════════════════ */
	$khoi = than_ham( $h, '_locLoaiTheoKhoi' );
	t( "🔴 $ban: màn HỎI CỜ lọc theo khối", '' !== $khoi, $khoi );
	t( "🔴 $ban: thiếu cờ thì rơi về CÓ LỌC (so `===false`, không dùng `!`)",
		false !== strpos( $khoi, 'BOOT.locLoaiTheoKhoi===false' ), $khoi );
	/* 23/09/2026: cửa khối đi qua `_loaiHopKhoi(x)` (chỉ so khi cùng trục miền/khối cũ) — cờ được hỏi
	   NGAY TRONG hàm ấy, và `_loaiCpList` cắt bằng nó. Ý phép không đổi: có hỏi cờ trước khi cắt. */
	$ds_ham = than_ham( $h, '_loaiCpList' );
	$hop_ham = than_ham( $h, '_loaiHopKhoi' );
	t( "🔴 $ban: ô chọn loại chi phí HỎI CỜ trước khi cắt theo khối",
		false !== strpos( $ds_ham, '!_loaiHopKhoi(x)' ) && false !== strpos( $hop_ham, 'if(!_locLoaiTheoKhoi()) return true;' ), $ds_ham . "\n---\n" . $hop_ham );

	/* 🔴 CHIỀU NGƯỢC LẠI — ĐỪNG GỠ LẠM. Anh Thắng nói tiếp: *"Khối là để xác định tài khoản
	   nợ"*. Tức khối THÔI làm cổng của DANH MỤC, nhưng VẪN là trục của TK NỢ: cùng một loại
	   chi phí, khác khối thì khác mã. Quét sạch mọi chỗ đọc khối là mọi dòng chi rơi vào một
	   mã duy nhất — sai sổ kế toán, và sai im lặng vì dòng nào cũng có mã trông hợp lệ. */
	t( "🔴 $ban: bảng TK Nợ VẪN chia theo khối — khối là trục của tài khoản nợ",
		false !== strpos( $h, '🔢 TK Nợ · <span' ) && false !== strpos( $h, '_tenKhoi(g.khoi)' ), null );
	$tkno = than_ham( $h, '_tkNoCua' );
	t( "   và đường tra mã của một dòng chi không bị đụng vào",
		'' !== $tkno && false === strpos( $tkno, 'KHOI_DANG' ), $tkno );

	$vai = than_ham( $h, '_vaiDungDuocLoai' );
	t( "🔴 $ban: màn HỎI CỜ, không tự suy",
		false !== strpos( $vai, 'BOOT.locLoaiTheoVai===false' ), $vai );
	/* ⚠️ Gói khởi động của bản CŨ không có khoá này. Thiếu khoá phải rơi về hành vi CŨ (có
	   lọc), không phải im lặng mở toang danh mục — nên phép so phải là `===false`. */
	t( "🔴 $ban: thiếu cờ thì rơi về CÓ LỌC (so `===false`, không dùng `!`)",
		false === strpos( $vai, '!BOOT.locLoaiTheoVai' ), $vai );

	/* 🔴 HÀNG VẼ BỞI BẢN CŨ KHÔNG CÓ Ô ĐẦU MỤC. Đọc ra rỗng rồi ghi đè là một lượt Lưu xoá
	   sạch công gán của kế toán — im lặng, vì bảng lưu xong vẽ lại trông vẫn bình thường. */
	/* 🔴 TẦNG THỨ BA — CHI PHÍ CON. Hành vi do `kiem-chi-phi-con.js` canh; ở đây chỉ đòi CẢ
	   BỐN BẢN đều có, vì bản vùng sinh lại từ gốc và sót một bản là mất tính năng không ai
	   biết cho tới lúc kế toán đi tìm cái nút ＋ không còn ở đó. */
	$row2 = than_ham( $h, '_mxRowHtml' );
	t( "🔴 $ban: mỗi dòng có nút ＋ thêm CHI PHÍ CON",
		false !== strpos( $row2, 'addCfgCon(this)' ), $row2 );
	t( "$ban: và dòng con chở theo ô `cha` (ô ẩn, không để gõ tay)",
		false !== strpos( $row2, 'data-o="cha"' ) && false !== strpos( $row2, 'type="hidden"' ), $row2 );
	$con = than_ham( $h, 'addCfgCon' );
	t( "🔴 $ban: bấm ＋ thì dòng con KẾ THỪA đầu mục và khối của cha",
		false !== strpos( $con, 'dauMuc:' ) && false !== strpos( $con, 'khoi:' ), $con );
	t( "$ban: chưa đặt tên cha thì chối, không tạo dòng con mồ côi ngay từ đầu",
		false !== strpos( $con, "if(!ten)" ), $con );
	/* ⚠️ PHÉP SOI CHỮ, và ở đây là CHẤP NHẬN ĐƯỢC: `addCfgCon()` chỉ sống bằng DOM thật
	   (`closest`, `querySelector`, `insertAdjacentHTML`), dựng cả một cây giả chỉ để đo một
	   dòng logic thì bệ đỡ còn dễ sai hơn thứ nó đo. Soi đúng cái quyết định: bấm ＋ trên một
	   dòng ĐÃ LÀ CON thì lấy cha CỦA NÓ, tức dòng mới thành ANH EM chứ không thành cháu —
	   cây ba tầng là đủ, mỗi tầng thêm là một tầng phải bóc ở mọi chỗ đọc sổ. */
	t( "🔴 $ban: bấm ＋ trên một dòng CON thì ra ANH EM của nó, không ra cháu",
		false !== strpos( $con, 'querySelector(\'[data-o="cha"]\')' )
		&& false !== strpos( $con, '|| ten' ), $con );

	$luu = than_ham( $h, 'saveCfgTkNoMx' );
	t( "🔴 $ban: lưu chỉ ghi `cha` KHI Ô CÓ MẶT — hàng bản cũ không có ô, ghi đè là cắt đứt "
		. "mọi dòng con khỏi cha",
		false !== strpos( $luu, 'oCha ?' ) && false !== strpos( $luu, 'goc.cha' ), $luu );
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
