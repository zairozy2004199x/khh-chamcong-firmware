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
	if ( preg_match( '#<style>([\s\S]*?)</style>#', $s, $m ) ) { return $m[1]; }
	return $s;
}

$TRANG = array(
	'trang Tổng'      => $GOC . '/wordpress/vhcp-chi-phi-tong/templates/app.html',
	'Chi phí (gốc)'   => $GOC . '/wordpress/vhcp-chi-phi/assets/css/vhcp.css',
	'Chi phí «mtd»'   => $GOC . '/wordpress/vhcp-chi-phi-mtd/assets/css/vhcp.css',
	'Chi phí «vp»'    => $GOC . '/wordpress/vhcp-chi-phi-vp/assets/css/vhcp.css',
	'trang Ghế (tối)' => $GOC . '/wordpress/vhcp-ghe/includes/class-vhg-trang.php',
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
	foreach ( array( 'trang Tổng', 'Chi phí (gốc)', 'Chi phí «mtd»', 'Chi phí «vp»' ) as $ten ) {
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
$g = $css['trang Ghế (tối)'];
t( '🔴 trang Ghế giữ nền TỐI, không nhận nền kem của bản sáng',
	false === strpos( $g, '--nen:#f9f8f6' ) && (bool) preg_match( '#--nen:\s*\#1[0-9a-f]{5}#i', $g ), '' );
t( '🔴 và giữ VÀNG làm dấu nhận mặt', (bool) preg_match( '#--nhan:\s*\#f0b429#i', $g ), '' );
/* Ảnh nền và lớp phủ tối là cặp không tách rời: bỏ lớp phủ thì chữ trắng nằm trên vùng sáng
   của ảnh là không đọc nổi. */
$ghe_php = file_get_contents( $GOC . '/wordpress/vhcp-ghe/includes/class-vhg-trang.php' );
t( '⚠️ ảnh nền còn nguyên', false !== strpos( $ghe_php, 'body.co-anh::before' ), '' );
t( '⚠️ và lớp phủ tối đi kèm cũng còn', false !== strpos( $ghe_php, 'body::after' ), '' );

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
echo "\n✓ SẠCH — $dat phép: năm trang cùng một bộ áo, sáng và tối chỉ khác màu.\n";
