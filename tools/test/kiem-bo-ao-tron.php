<?php
/**
 * BỘ ÁO TRỘN PHẢI LÀ MỘT BỘ — SÁNG VÀ TỐI CHỈ KHÁC MÀU.
 *
 * Anh Thắng 14/09/2026 gửi hai tệp DESIGN.md, chọn *"Trộn hai bản"*, xem thử trên trang tổng
 * rồi bảo *"thay cho trang ghế và chi phí văn phòng và mtd đi em"*.
 *
 * =============================================================================================
 * 🔴 VÌ SAO CÓ TỆP NÀY. Bộ áo nay nằm ở BỐN chỗ rời nhau — trang Tổng, bộ dùng chung của Chi
 *    phí (bản gốc, và «vp» «mtd» sinh ra từ đó), và trang Ghế. Không có gì buộc chúng đi cùng
 *    nhau: sửa bo góc ở một chỗ rồi quên ba chỗ kia thì KHÔNG AI BÁO — bốn trang cứ lệch dần,
 *    và mỗi lần lệch lại tốn một vòng "sao trang này khác trang kia".
 *
 * 🔴 SÁNG VÀ TỐI KHÁC MÀU, KHÔNG KHÁC HÌNH. Trang Ghế phải tối: nó nằm trên một tấm ảnh phòng
 *    ghế, là màn cho khách ở trung tâm thương mại, nhân viên đọc số từ đầu bên kia quầy. Nên
 *    bài này KHÔNG đòi nó cùng màu — nó đòi cùng TÊN BIẾN, cùng BO GÓC, cùng NHỊP 4px. Đó
 *    đúng là phần "cùng một bộ".
 * =============================================================================================
 */
$GOC = dirname( dirname( __DIR__ ) );
$dat = 0; $truot = array();
function t( $ten, $dk, $them = '' ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( '' === $them ? '' : "\n      → " . $them );
}

/**
 * Bốc khối CSS ra khỏi một tệp.
 *
 * ⚠️ HỎI HEREDOC TRƯỚC, `<style>` SAU. Tệp PHP của trang Ghế có một chuỗi `'<style>'` nằm
 *    trong mã PHP (chỗ nó ghép thẻ ra HTML), nên hỏi `<style>` trước là bốc trúng mẩu mã PHP
 *    ấy chứ không phải bộ áo — và cả 15 phép dưới đây đỏ oan, chỉ ra một danh sách "thiếu 24
 *    tên biến" trông y như bộ áo chưa làm.
 */
function css_cua( $duong ) {
	$s = @file_get_contents( $duong );
	if ( ! is_string( $s ) ) { return ''; }
	if ( preg_match( "#return <<<'CSS'\n([\s\S]*?)\nCSS;#", $s, $m ) ) { return $m[1]; }
	/* 🔴 NHÁNH NÀY PHẢI ĐỨNG TRƯỚC NHÁNH `<style>`, cùng lý do đã kể ở trên và nặng hơn.
	   `VHCC_Web::css()` không phải heredoc mà là một chuỗi PHP nối bằng dấu chấm; còn chính tệp
	   ấy có một chuỗi `'<style>'` nằm trong mã PHP (chỗ nó ghép thẻ <head>). Hỏi `<style>` trước
	   là bốc trúng từ mẩu mã PHP ấy tới thẻ `</style>` đầu tiên — tức là bốc trúng MÃ PHP, không
	   phải bảng kiểu, và cả bài đỏ oan bằng một danh sách "thiếu 24 tên biến" trông y như bộ áo
	   chưa làm. Bóc đúng thân hàm `css()` rồi gỡ dấu nháy và dấu nối thì ra bảng kiểu thật. */
	if ( preg_match( '#public static function css\(\)\s*\{([\s\S]*?)\n\t\}#', $s, $m ) ) {
		$than = preg_replace( '#/\*[\s\S]*?\*/#', '', $m[1] );   // bỏ chú thích
		$than = preg_replace( '#//[^\n]*#', '', $than );
		$ra   = '';
		if ( preg_match_all( "#'((?:[^'\\\\]|\\\\.)*)'#", $than, $mm ) ) {
			foreach ( $mm[1] as $doan ) { $ra .= str_replace( array( "\\'", '\\\\' ), array( "'", '\\' ), $doan ); }
		}
		return $ra;
	}
	if ( preg_match( '#<style>([\s\S]*?)</style>#', $s, $m ) ) { return $m[1]; }
	return $s;
}

$TRANG = array(
	'trang Tổng'      => $GOC . '/wordpress/vhcp-chi-phi-tong/templates/app.html',
	'Chi phí (gốc)'   => $GOC . '/wordpress/vhcp-chi-phi/assets/css/vhcp.css',
	'Chi phí «mtd»'   => $GOC . '/wordpress/vhcp-chi-phi-mtd/assets/css/vhcp.css',
	'Chi phí «vp»'    => $GOC . '/wordpress/vhcp-chi-phi-vp/assets/css/vhcp.css',
	/* ⚠️ `trang Ghế` GỠ KHỎI DANH SÁCH 25/09/2026 — không phải vì thôi cần canh, mà vì thư mục
	   `wordpress/vhcp-ghe/` đã RỜI nhánh này (bản 1.48.0 là mã chết, bản thật 2.111.0 sống ở
	   nhánh `claude/posh-qr-kh1urz`; xem `wordpress/DOC-TRUOC-KHI-DONG-GOI.md`). Soi một đường
	   dẫn không còn tồn tại thì bài này đỏ mãi mà chẳng canh gì. Muốn canh bộ áo của Ghế thì
	   canh Ở NHÁNH CỦA NÓ. */
	/* ══════════════════════════════════════════════════════════════════════════════════════
	 * HAI TRANG VÀO THÊM 16/09/2026 — anh Thắng: *"chỉnh lại toàn trang thống nhất"*.
	 *
	 * Chấm Công là plugin CUỐI CÙNG đứng ngoài bộ áo này. Nó có bảng màu riêng (`#f6f4f1`),
	 * bo góc riêng (10px chứ không 16px), không có nhịp `--d*` — và KHÔNG tệp thử nào buộc nó
	 * theo ai, nên nó lệch dần mà không có gì báo. Đó đúng là chuyện bài này sinh ra để chặn.
	 *
	 * `VHCC_Web::css()` là bảng kiểu của BẢY màn ngoài web (bảng công, Nhân sự, Khuôn mặt,
	 * Phân lịch, Nhân sự cửa hàng, Máy & Firmware, và các màn phụ) — buộc được một chỗ này là
	 * buộc cả bảy.
	 * ══════════════════════════════════════════════════════════════════════════════════════ */
	'Chấm Công (sáng)' => $GOC . '/wordpress/vhcp-cham-cong/includes/class-vhcc-web.php',
	'Trạm bấm (tối)'   => $GOC . '/wordpress/vhcp-cham-cong/templates/tram.php',
);

/* ═══ 1. MỌI TRANG PHẢI KHAI ĐỦ BỘ TÊN BIẾN ════════════════════════════════════
 *
 * Thiếu một tên ở một trang thì `var(--x)` ở đó trả rỗng và trình duyệt LẶNG LẼ bỏ qua cả
 * dòng ấy — không lỗi, không cảnh báo, chỉ là cái viền hay cái bo góc biến mất. */
$TEN = array( '--nen','--the','--nen-2','--vien','--vien-dam','--chu','--chu-dam','--chu-mo',
	'--nhan','--nhan-dam','--nhan-nhat','--do',
	'--d1','--d2','--d3','--d4','--d5','--d6',
	'--bo-the','--bo-nut','--bo-o','--bo-o-bang','--bo-nho','--bo-badge' );
$css = array();
foreach ( $TRANG as $ten => $duong ) {
	$css[ $ten ] = css_cua( $duong );
	t( 'đọc được bộ áo của ' . $ten, '' !== $css[ $ten ], $duong );
	$thieu = array();
	foreach ( $TEN as $b ) {
		if ( ! preg_match( '#' . preg_quote( $b, '#' ) . '\s*:#', $css[ $ten ] ) ) { $thieu[] = $b; }
	}
	t( '🔴 ' . $ten . ' khai đủ ' . count( $TEN ) . ' tên biến', ! $thieu, implode( ', ', $thieu ) );
}

/* ═══ 2. KHÔNG TRANG NÀO DÙNG TÊN CHƯA KHAI ════════════════════════════════════ */
foreach ( $TRANG as $ten => $duong ) {
	preg_match_all( '#var\(\s*(--[a-z0-9-]+)#', $css[ $ten ], $dung );
	preg_match_all( '#(--[a-z0-9-]+)\s*:\s*[^;]+;#', $css[ $ten ], $khai );
	$thieu = array_values( array_unique( array_diff( $dung[1], $khai[1] ) ) );
	t( '⚠️ ' . $ten . ': không token nào dùng mà chưa khai', ! $thieu, implode( ', ', $thieu ) );
}

/* ═══ 3. HÌNH PHẢI GIỐNG NHAU TUYỆT ĐỐI ════════════════════════════════════════
 *
 * 🔴 ĐÂY LÀ CHỐT CHÍNH. Màu thì sáng/tối khác nhau là đúng; BO GÓC và NHỊP thì không —
 *    lệch một con số ở đây là hai trang mở cạnh nhau trông như hai phần mềm khác nhau. */
$HINH = array(
	'--d1' => '4px', '--d2' => '8px', '--d3' => '12px', '--d4' => '16px',
	'--d5' => '20px', '--d6' => '24px',
	'--bo-the' => '16px', '--bo-nut' => '18px', '--bo-o' => '10px',
	'--bo-o-bang' => '6px', '--bo-nho' => '8px', '--bo-badge' => '16px',
);
foreach ( $HINH as $bien => $gt ) {
	$lech = array();
	foreach ( $TRANG as $ten => $_ ) {
		if ( ! preg_match( '#' . preg_quote( $bien, '#' ) . '\s*:\s*([^;]+);#', $css[ $ten ], $m ) ) {
			$lech[] = $ten . ' (không có)';
		} elseif ( trim( $m[1] ) !== $gt ) {
			$lech[] = $ten . ' = ' . trim( $m[1] );
		}
	}
	t( '🔴 ' . $bien . ' = ' . $gt . ' ở CẢ NĂM trang', ! $lech, implode( ' · ', $lech ) );
}

/* ═══ 4. BỐN BẢN SÁNG PHẢI CÙNG MỘT BẢNG MÀU ═══════════════════════════════════
 *
 * Trang Ghế đứng ngoài mục này — nó là mặt TỐI, xem chú thích đầu tệp. */
$MAU_SANG = array( '--nen' => '#f9f8f6', '--the' => '#ffffff', '--vien' => '#f0e9e1',
	'--chu-dam' => '#0c1754', '--nhan' => '#2545ff', '--do' => '#e7000b' );
foreach ( $MAU_SANG as $bien => $gt ) {
	$lech = array();
	foreach ( array( 'trang Tổng', 'Chi phí (gốc)', 'Chi phí «mtd»', 'Chi phí «vp»', 'Chấm Công (sáng)' ) as $ten ) {
		if ( ! preg_match( '#' . preg_quote( $bien, '#' ) . '\s*:\s*([^;]+);#', $css[ $ten ], $m )
			|| trim( $m[1] ) !== $gt ) {
			$lech[] = $ten;
		}
	}
	t( '🔴 bản sáng: ' . $bien . ' = ' . $gt, ! $lech, implode( ' · ', $lech ) );
}

/* ═══ 5. TRANG GHẾ PHẢI Ở LẠI MẶT TỐI ══════════════════════════════════════════
 *
 * 🔴 Không phải chuyện thẩm mỹ. Trang này nằm TRÊN MỘT TẤM ẢNH phòng ghế và là màn cho khách
 *    ở trung tâm thương mại: nhân viên cầm một tay, tay kia giữ ngăn tiền, đọc số từ đầu bên
 *    kia quầy. Đổ nền kem lên đó thì hoặc che mất ảnh, hoặc chữ tối rơi vào vùng sáng của ảnh —
 *    mà ảnh nào cũng có một vùng sáng ở đâu đó. Ai đó "cho đồng bộ" bằng cách dán bảng màu
 *    sáng vào đây thì phép này phải đỏ. */
/* Ảnh nền và lớp phủ tối là cặp không tách rời: bỏ lớp phủ thì chữ trắng nằm trên vùng sáng
   của ảnh là không đọc nổi. */
/* Trạm bấm: MÀN CHỤP phải tối, và vì một lý do làm được chứ không phải vì đẹp — nó mở camera
   soi mặt để chấm công và chạy cả ca đêm. Màn sáng thì hắt thẳng vào mặt người đang đứng chụp,
   ảnh bệt, mà đúng tấm ảnh ấy là thứ quản lý dùng đối chiếu về sau.

   ⚠️ 17/09/2026 — CANH MÀN CHỤP, KHÔNG CANH CẢ TRANG. Từ bản 4.29.1 trạm đổi sang mặt sáng cho
      hợp với bảng công và ba trang Chi phí; chỉ năm giây đứng chụp mới có chuyện hắt sáng, nên
      chữa đúng chỗ ấy là đủ. Chính chú thích đầu `tram.php` đã chốt hướng này.

      Nhưng lúc hoà nhánh mới lộ ra là hướng ấy MỚI ĐƯỢC VIẾT RA CHỨ CHƯA ĐƯỢC LÀM: `#mChup`
      không có lấy một luật màu, và bài kiểm này cũng chưa hề đổi như chú thích nói. Tức trạm
      đã sáng toàn bộ, kể cả lúc chụp, suốt từ đó. Nay `#mChup` có bảng màu tối riêng và phép
      dưới đây canh đúng nó — canh cả trang nữa thì đỏ oan, mà bỏ hẳn thì mất luôn cái gác. */
$tr = $css['Trạm bấm (tối)'];
$m_chup = '';
if ( preg_match( '~#mChup\s*\{(.*?)\}~s', $tr, $m ) ) { $m_chup = $m[1]; }
t( '🔴 màn chụp của Trạm có bảng màu riêng', '' !== $m_chup, '' );
t( '🔴 màn chụp giữ nền TỐI, không nhận nền kem của bản sáng',
	false === strpos( $m_chup, '--nen:#f9f8f6' ) && (bool) preg_match( '#--nen:\s*\#1[0-9a-f]{5}#i', $m_chup ), '' );


/* ═══ 6. KHÔNG TRANG NÀO TẢI FONT NGOÀI ════════════════════════════════════════
 *
 * Cả hai tệp DESIGN đòi font riêng (Geist · Martinaplantijn · ABC Favorit). Mấy trang này
 * chạy ngoài internet trên hosting: thêm một lượt tải font là thêm một phụ thuộc mạng, và
 * mạng chậm thì chữ nhảy một nhịp giữa lúc người ta đang gõ số tiền hoặc đếm ngăn tiền. */
foreach ( $TRANG as $ten => $duong ) {
	$tep = file_get_contents( $duong );
	t( '🔴 ' . $ten . ': không tải font ngoài',
		false === strpos( $css[ $ten ], '@import' ) && false === strpos( $tep, 'fonts.googleapis' ), '' );
}

/* ═══ 7. NỀN 3D — VÀ NÓ PHẢI ĐỨNG YÊN ══════════════════════════════════════════
 *
 * Anh Thắng 16/09/2026: *"nền 3D"*. Cách làm: ba vầng sáng rất loãng nằm phía SAU mọi thẻ,
 * thẻ thì đục — nên thẻ nổi lên khỏi nền, và đó là chỗ ra chiều sâu.
 *
 * 🔴 `background-attachment:fixed` là NỬA KHÔNG ĐƯỢC BỎ. Lưới 31 cột vừa cuộn dọc vừa cuộn
 *    ngang; nền chạy theo là cả trang trôi, nhìn một phút là mỏi mắt. Gỡ đúng một chữ ấy thì
 *    trang vẫn "có nền 3D" và trông vẫn đẹp trong ảnh chụp — chỉ hỏng lúc người ta cuộn. Đó
 *    là loại hỏng không ai báo, nên phải có phép canh. */
$cs = $css['Chấm Công (sáng)'];
$tr = $css['Trạm bấm (tối)'];
t( '🔴 Chấm Công: nền trang có đủ BA vầng sáng gradient',
	3 === preg_match_all( '#radial-gradient\(#', $cs ), 'đếm được ' . preg_match_all( '#radial-gradient\(#', $cs ) );
t( '🔴 và nền ấy ĐỨNG YÊN khi cuộn (background-attachment:fixed)',
	false !== strpos( $cs, 'background-attachment:fixed' ), '' );
t( '🔴 Trạm bấm cũng vậy — cùng ba vầng, cũng đứng yên',
	preg_match_all( '#radial-gradient\(#', $tr ) >= 3 && false !== strpos( $tr, 'background-attachment:fixed' ), '' );
/* Chiều sâu phải có ĐỦ BA TẦNG. Một tầng dùng chung thì hoặc thẻ nào cũng nổi bồng bềnh, hoặc
   thứ đang được chạm chẳng khác gì thứ đứng yên — mà chiều sâu chỉ đọc được khi có thứ để so. */
foreach ( array( 'Chấm Công (sáng)' => $cs, 'Trạm bấm (tối)' => $tr ) as $ten => $_c ) {
	$thieu = array();
	foreach ( array( '--bong', '--bong-2', '--bong-3' ) as $b ) {
		if ( ! preg_match( '#' . preg_quote( $b, '#' ) . '\s*:#', $_c ) ) { $thieu[] = $b; }
	}
	t( '⚠️ ' . $ten . ' khai đủ ba tầng bóng đổ', ! $thieu, implode( ', ', $thieu ) );
}

/* ═══ 8. IN RA GIẤY THÌ HUỶ NỀN 3D VÀ MỌI BÓNG ĐỔ ══════════════════════════════
 *
 * 🔴 Bảng lương in ra A4 có 27 cột phủ kín tờ giấy. Ba vầng gradient trở thành một lớp tím
 *    nhạt trải hết tờ: máy in phun ăn hết mực, và chữ đen nằm trên nền màu thì mờ hẳn. Bóng đổ
 *    cũng vậy — trên màn là chiều sâu, trên giấy là một vệt xám bẩn quanh mỗi thẻ.
 *
 * ⚠️ SOI ĐÚNG KHỐI `@media print`, KHÔNG SOI CẢ CHUỖI. Tìm `background:#fff` trong cả bảng kiểu
 *    thì luôn thấy (thẻ nào chẳng nền trắng) và phép hoá ra không canh gì — cái bẫy đã cắn
 *    nhiều lần ở bộ thử bên chấm công. */
$in = '';
if ( preg_match( '#@media print\{([\s\S]*?\}\})#', $cs, $m_in ) ) { $in = $m_in[1]; }
t( '🔴 có khối @media print', '' !== $in, 'không bóc được khối in' );
t( '🔴 in ra giấy thì nền về TRẮNG', false !== strpos( $in, 'body{background:#fff!important}' ), $in );
t( '🔴 và mọi bóng đổ bị huỷ', false !== strpos( $in, 'box-shadow:none!important' ), $in );

/* ═══ 9. KHÔNG CÒN MÃ MÀU GÕ CỨNG TRONG `style="` ══════════════════════════════
 *
 * 🔴 ĐÂY LÀ PHÉP BÉN NHẤT CỦA CẢ BÀI, và là thứ mục 1-4 không với tới.
 *    Mã màu gõ thẳng vào `style="color:#b32d2e"` KHÔNG theo bất cứ thay đổi nào ở `:root`.
 *    Trước 16/09/2026 plugin Chấm Công có 58 chỗ như thế — đổi bảng màu là 58 chỗ ấy giữ
 *    nguyên màu cũ, và trang lệch dần đúng theo cơ chế bài này sinh ra để chặn.
 *
 * ⚠️ MIỄN THEO TÊN CHUỖI, KHÔNG MIỄN THEO TỆP. Miễn cả một tệp là hôm sau ai đó dán mã màu vào
 *    đúng tệp ấy và phép vẫn xanh. Hiện KHÔNG có chuỗi nào cần miễn; danh sách để rỗng có chủ
 *    ý, và ai thêm vào đây phải viết kèm lý do. */
$MIEN = array();
$VE   = glob( $GOC . '/wordpress/vhcp-cham-cong/includes/*.php' );
$VE   = array_merge( $VE, glob( $GOC . '/wordpress/vhcp-cham-cong/templates/*.php' ) );
$dinh = array();
foreach ( $VE as $tep ) {
	$noi = (string) @file_get_contents( $tep );
	/* ⚠️ BỎ CHÚ THÍCH KHỐI RA TRƯỚC KHI SOI. 17/09/2026: `class-vhcc-ve-tram.php` viết trong
	   chú thích của chính nó rằng nó KHÔNG dùng `style="` dán thẳng — và đúng là không dùng.
	   Nhưng mẫu `style="[^"]*"` vớ ngay mấy chữ `style="` trong câu chú thích ấy rồi quét tiếp
	   tới dấu " kế, nuốt luôn khối <style> bên dưới và báo oan hai mã màu trong đó.

	   Báo oan ở đây đắt hơn vẻ ngoài: phép này là phép BÉN NHẤT của cả bài, và nó đã một mình
	   giữ `phat-hanh.yml` đỏ suốt — không plugin nào trong kho tạo được tag. Một bài kiểm đỏ vì
	   lý do không phải lỗi là bài người ta tắt đi, rồi mất luôn cả cái gác thật.

	   Bỏ theo tên tệp thì trái chính lời bài này dặn ("MIỄN THEO TÊN CHUỖI, KHÔNG MIỄN THEO
	   TỆP"), nên bỏ đúng thứ đáng bỏ: chữ trong chú thích không phải là mã chạy. */
	$noi = (string) preg_replace( '~/\*[\s\S]*?\*/~', ' ', $noi );
	/* Chỉ soi TRONG thuộc tính `style="…"`. Mã màu trong bảng kiểu ở `<style>` là chuyện khác:
	   ở đó nó nằm cạnh tên biến, sửa một chỗ là xong. */
	if ( ! preg_match_all( '#style="[^"]*"#', $noi, $m_st ) ) { continue; }
	foreach ( $m_st[0] as $st ) {
		if ( in_array( $st, $MIEN, true ) ) { continue; }
		/* 🔴 `var(--x,#hex)` KHÔNG TÍNH LÀ GÕ CỨNG — gỡ nó ra trước khi đếm.
		   Vài khối phải dựng được cả khi bảng kiểu CHƯA có: hai màn báo lỗi 500 (chúng hiện ra
		   đúng lúc trang chính đã nổ) và ô nhập PIN dùng chung (`VHCC_Phien::o_pin()`, có hứa
		   ngay trong chú thích là "trang mới nào cũng dùng được ngay"). Ở đó mã màu là GIÁ TRỊ
		   LUI đứng sau một tên token — có bộ áo thì ăn theo bộ áo, không có thì vẫn đọc được.
		   Đó là cách đúng, không phải chỗ cần cấm. Thứ bài này cấm là mã màu đứng MỘT MÌNH,
		   loại không theo bất cứ thay đổi nào ở `:root`. */
		$st_soi = preg_replace( '~var\(\s*--[a-z0-9-]+\s*,[^()]*\)~i', '', $st );
		if ( preg_match( '~#[0-9a-fA-F]{3,8}\b~', $st_soi ) ) { $dinh[] = basename( $tep ) . ': ' . $st; }
		/* ⚠️ Dấu phân cách `~`, KHÔNG phải `#` — mẫu cần tìm CHÍNH LÀ ký tự `#`. Dùng `#` làm
		   dấu phân cách thì `preg_match` trả `false` kèm một Warning, mà `false` ở đây nghĩa là
		   "không thấy mã màu nào" — phép xanh trong khi nó chẳng soi gì cả. Bản nháp đã dính đúng
		   lỗi ấy và in ra "✓ SẠCH" với 58 chỗ gõ cứng còn nguyên. */

	}
}
t( '🔴 không một mã màu nào gõ cứng trong style=" của plugin Chấm Công',
	! $dinh, implode( "\n      → ", array_slice( $dinh, 0, 8 ) ) );

/* ═══ 10. MÀU BÁO LỖI CÒN NGUYÊN VÀ CÒN PHÂN BIỆT ĐƯỢC ═════════════════════════
 *
 * 🔴 Lưới chấm công dùng màu để BÁO LỖI, không phải để trang trí: đỏ = ghi sai, vàng = trễ,
 *    tím = nghỉ có đơn, lục = đủ công, rồi bốn sắc cho bốn ca. Một lần "cho đồng bộ màu" là
 *    mọi cảnh báo biến mất mà bảng vẫn trông đẹp — hỏng đúng kiểu không ai báo.
 *
 * Nên canh HAI vế: các lớp ấy còn có mặt, VÀ chúng còn khác nhau đôi một. Chỉ canh vế đầu thì
 * gán cả tám lớp cùng một màu vẫn xanh. */
$LOP = array( 'table.cc td.oc.hong', 'table.cc td.oc.vang', 'table.cc td.oc.tim', 'table.cc td.oc.luc' );
$nen = array();
foreach ( $LOP as $lop ) {
	if ( preg_match( '#' . preg_quote( $lop, '#' ) . '\{[^}]*background:([^;}]+)#', $cs, $m_l ) ) {
		$nen[ $lop ] = trim( $m_l[1] );
	}
}
t( '🔴 bốn ô báo (sai · trễ · đơn · đủ) đều còn luật nền', 4 === count( $nen ), implode( ', ', array_keys( $nen ) ) );
t( '🔴 và bốn nền ấy KHÁC NHAU đôi một', 4 === count( array_unique( $nen ) ), implode( ' · ', $nen ) );
$ca = array();
foreach ( array( 1, 2, 3, 4 ) as $i ) {
	if ( preg_match( '#td\.oc\.ca' . $i . ',table\.cc th\.ca' . $i . '\{background:([^;}]+)#', $cs, $m_c ) ) {
		$ca[ $i ] = trim( $m_c[1] );
	}
}
t( '🔴 bốn sắc CA đều còn', 4 === count( $ca ), implode( ', ', array_keys( $ca ) ) );
t( '🔴 và bốn sắc ca KHÁC NHAU đôi một', 4 === count( array_unique( $ca ) ), implode( ' · ', $ca ) );
/* Ô thiếu giờ ra và hàng thiếu giờ ra là HAI luật khác nhau (một tô ô, một tô cả `<tr>`); mất
   luật thứ hai thì thuộc tính có mà màu không lên. */
t( '⚠️ cả Ô lẫn HÀNG thiếu giờ ra đều còn luật nền đỏ',
	false !== strpos( $cs, 'table.cc td.hong{background:var(--do-nhat)' )
	&& false !== strpos( $cs, 'table.cc tr.hong>td{background:var(--do-nhat)}' ), '' );

/* ═══════════════════════════════════════════════════════════════════════════════
 * Khối báo trượt đứng CUỐI CÙNG — thêm mục mới thì thêm Ở TRÊN chỗ này.
 * (14/09/2026 một tệp thử khác đã dính đúng bẫy ấy: khối báo nằm giữa tệp nên phần thêm sau
 *  chạy xong mà kết quả rơi vào hư không, gỡ hẳn một cái gác bài vẫn in "✓ SẠCH".)
 * ═══════════════════════════════════════════════════════════════════════════════ */
if ( $truot ) {
	echo "\n✗ TRƯỢT " . count( $truot ) . " phép (đạt $dat):\n";
	foreach ( $truot as $x ) { echo "  · $x\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $dat phép: sáu trang cùng một bộ áo, sáng và tối chỉ khác màu.\n";
