<?php
/**
 * KIỂM DẢI NHẮC CHỖ CHẤM CÔNG trên trang Nội bộ (VHNB_Nhac).
 *
 * 🔴 VÌ SAO MỘT DẢI THÔNG BÁO CŨNG CẦN BÀI THỬ. Nó hiện trên MỌI trang Nội bộ, cho MỌI nhân
 *    viên, mỗi ngày. Một thông báo hỏng không làm mất dữ liệu của ai — nó làm một việc âm thầm
 *    tệ hơn: dạy cả công ty bỏ qua thông báo của hệ thống này. Sau đó thì lần có việc thật, cái
 *    dải ấy cũng không ai đọc.
 *
 *    Nên bài này canh đúng ba thứ khiến người ta bắt đầu bỏ qua: nó có tự đi không, nó có nhớ
 *    khi người ta bảo thôi không, và nó có tự chết khi hết việc không.
 *
 * Chạy: php tools/test/kiem-nhac-cho-cham-cong.php
 */

require_once __DIR__ . '/wp-stub.php';

$dat = 0; $truot = array();

function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( null === $them ? '' : ' — ' . ( is_scalar( $them ) ? $them : var_export( $them, true ) ) );
}

$goc = dirname( dirname( __DIR__ ) );
$bo  = $goc . '/wordpress/vhcp-noi-bo';

if ( ! defined( 'VHNB_VERSION' ) ) { define( 'VHNB_VERSION', 'thu' ); }
require_once $bo . '/includes/class-vhnb-nhac.php';

/* ═══════════════════════════════════════════════════════════════════ 1. MẶC ĐỊNH */

/* 🔴 MẶC ĐỊNH PHẢI LÀ TẮT. Cài bản mới lên mà cả công ty bỗng thấy một dải lạ ở đáy màn — mà
   quản trị chưa kịp khai địa chỉ nào — là mất lòng tin vào chính lượt cập nhật. */
$c = VHNB_Nhac::cai();
t( '🔴 mặc định TẮT', empty( $c['bat'] ), $c );
t( 'mặc định 10 giây', 10 === (int) $c['giay'], $c );
t( 'mặc định không có hạn', '' === (string) $c['han'], $c );
t( '🔴 chưa bật thì không vẽ', ! VHNB_Nhac::nen_ve() );

/* ═══════════════════════════════════════════════════════════════ 2. KHÔNG BIẾT DẪN ĐI ĐÂU */

/* 🔴 BẬT MÀ KHÔNG CÓ ĐỊA CHỈ THÌ VẪN KHÔNG VẼ. Một dải mời "bấm vào đây để chấm công" mà nút
   dẫn tới chuỗi rỗng là đưa cả công ty về trang chủ, rồi họ kết luận trang chấm công hỏng. */
VHNB_Nhac::dat( array( 'bat' => true, 'dich' => '', 'giay' => 10 ) );
t( '🔴 bật nhưng chưa có địa chỉ đích -> vẫn KHÔNG vẽ', ! VHNB_Nhac::nen_ve() );

/* Có plugin Chấm Công thì tự hỏi nó — không ghi cứng `/cham-cong-online/`. */
if ( ! class_exists( 'VHCC_Tram' ) ) {
	eval( 'class VHCC_Tram { public static function url() { return "https://khmatrix.com/cham-cong-online/"; } }' );
}
t( '🔴 tự hỏi plugin Chấm Công khi ô địa chỉ để trống',
	'https://khmatrix.com/cham-cong-online/' === VHNB_Nhac::dich(), VHNB_Nhac::dich() );
t( 'có địa chỉ rồi thì vẽ', VHNB_Nhac::nen_ve() );

/* Khai tay thì khai tay thắng — dùng khi hai plugin ở hai website khác nhau. */
VHNB_Nhac::dat( array( 'bat' => true, 'dich' => 'https://noi-khac.vn/cc/' ) );
t( 'ô khai tay thắng địa chỉ tự hỏi',
	'https://noi-khac.vn/cc/' === VHNB_Nhac::dich(), VHNB_Nhac::dich() );

/* ═══════════════════════════════════════════════════════════════════ 3. SỐ GIÂY */

VHNB_Nhac::dat( array( 'bat' => true, 'giay' => 1 ) );
t( '🔴 số giây quá nhỏ bị kéo lên sàn ' . VHNB_Nhac::GIAY_IT,
	VHNB_Nhac::GIAY_IT === (int) VHNB_Nhac::cai()['giay'], VHNB_Nhac::cai()['giay'] );
VHNB_Nhac::dat( array( 'bat' => true, 'giay' => 9999 ) );
t( '🔴 số giây quá lớn bị kéo xuống trần ' . VHNB_Nhac::GIAY_NHIEU,
	VHNB_Nhac::GIAY_NHIEU === (int) VHNB_Nhac::cai()['giay'], VHNB_Nhac::cai()['giay'] );
VHNB_Nhac::dat( array( 'bat' => true, 'giay' => 10 ) );
t( 'số giây hợp lệ giữ nguyên', 10 === (int) VHNB_Nhac::cai()['giay'] );

/* ═══════════════════════════════════════════════════════════════════ 4. HẠN TỰ TẮT */

t( 'không hạn -> còn mãi', VHNB_Nhac::con_han( '2099-01-01' ) );
VHNB_Nhac::dat( array( 'bat' => true, 'han' => '2026-10-31' ) );
t( 'trước hạn -> còn', VHNB_Nhac::con_han( '2026-10-01' ) );
t( 'đúng ngày hạn -> vẫn còn', VHNB_Nhac::con_han( '2026-10-31' ) );
/* 🔴 QUA HẠN LÀ THÔI, dù ô "bật" vẫn tích. Đây là lý do cả cái hạn tồn tại: người bật nó
   thường không phải người nhớ tắt nó. */
t( '🔴 qua hạn -> KHÔNG vẽ nữa dù vẫn đang bật', ! VHNB_Nhac::con_han( '2026-11-01' ) );

VHNB_Nhac::dat( array( 'bat' => true, 'han' => 'ngày nào đó' ) );
t( 'hạn gõ bậy -> bỏ, không thành một ngày bịa', '' === (string) VHNB_Nhac::cai()['han'],
	VHNB_Nhac::cai()['han'] );

/* ═══════════════════════════════════════════════════════════════════ 5. VIDEO */

t( 'mp4 là phim', VHNB_Nhac::la_phim( 'https://x.vn/wp-content/uploads/2026/09/hd.mp4' ) );
t( 'mp4 kèm tham số vẫn là phim', VHNB_Nhac::la_phim( 'https://x.vn/hd.mp4?v=2' ) );
t( 'webm là phim', VHNB_Nhac::la_phim( 'https://x.vn/hd.webm' ) );
/* 🔴 YOUTUBE KHÔNG PHẢI PHIM PHÁT THẲNG. Nhét nó vào thẻ <video> thì khung đen im lặng —
   không lỗi, không ảnh, không gì. Phải thành một đường dẫn mở tab mới. */
t( '🔴 link YouTube KHÔNG phải phim phát thẳng',
	! VHNB_Nhac::la_phim( 'https://www.youtube.com/watch?v=abc123' ) );
t( 'link Drive không phải phim phát thẳng',
	! VHNB_Nhac::la_phim( 'https://drive.google.com/file/d/abc/view' ) );
t( 'rỗng không phải phim', ! VHNB_Nhac::la_phim( '' ) );

/* ═══════════════════════════════════════════════════════════════════ 6. CHỮ HIỆN RA */

/* Chữ do người gõ ở wp-admin, và nó đi thẳng vào trang mà mọi nhân viên mở. Một dấu nháy hay
   một thẻ <script> lọt qua là cả trang Nội bộ thành chỗ chạy mã của người gõ. */
VHNB_Nhac::dat( array(
	'bat'  => true,
	'dich' => 'https://khmatrix.com/cham-cong-online/',
	'tieu' => 'Chấm công <script>alert(1)</script>',
	'chu'  => "Dòng một\n<b>đậm</b>",
) );
$c = VHNB_Nhac::cai();
t( '🔴 thẻ script bị lọc ngay lúc LƯU, không đợi lúc vẽ',
	false === strpos( (string) $c['tieu'], '<script' ), $c['tieu'] );

ob_start();
VHNB_Nhac::ve();
$html = (string) ob_get_clean();

/* ⚠️ CẮT LẤY PHẦN MARKUP, bỏ khối <style> và <script> của chính dải. Khối CSS có khai
   `.vhnb-nhac-xem`, `.vhnb-nhac-phim` — dò cả vào đó thì phép thử "không có nút Xem hướng
   dẫn" luôn đỏ, kể cả khi nút ấy thật sự không được vẽ. */
function than( $h ) {
	$i = strpos( $h, '<style>' );
	return ( false === $i ) ? $h : substr( $h, 0, $i );
}
$than = than( $html );

t( 'có vẽ ra dải', false !== strpos( $than, 'id="vhnb-nhac"' ) );
/* 🔴 KHÔNG THẺ NÀO CỦA NGƯỜI GÕ LỌT RA THÀNH THẺ THẬT. `sanitize_text_field` bóc thẻ và giữ
   lại phần chữ bên trong — đúng như WordPress thật — nên chữ "alert(1)" CÓ hiện ra, dưới
   dạng chữ thường, vô hại. Thứ phải canh là không còn `<script` nào, không phải không còn
   chữ nào trông giống mã. */
t( '🔴 không thẻ script nào lọt vào trang', false === stripos( $than, '<script' ), $than );
t( '🔴 và dấu ngoặc nhọn của người gõ bị thoát hết',
	false === strpos( $than, '<b>Chấm công <' ), $than );
t( 'nút dẫn đúng địa chỉ',
	false !== strpos( $html, 'href="https://khmatrix.com/cham-cong-online/"' ), $html );
t( 'có nút "Đừng hiện nữa"', false !== strpos( $than, 'vhnb-nhac-thoi' ) );
t( 'có ô đếm ngược', false !== strpos( $than, 'vhnb-nhac-dem' ) );
t( 'có vạch chạy', false !== strpos( $than, 'vhnb-nhac-chay' ) );

/* 🔴 DẢI PHẢI ẨN SẴN (`hidden`) VÀ CHỈ HIỆN BẰNG JAVASCRIPT. Hiện sẵn trong HTML thì người đã
   bấm "Đừng hiện nữa" vẫn thấy nó nháy một cái ở mỗi lượt tải trang, trước khi script kịp
   giấu đi — và một cái nháy ở đáy màn mỗi lần mở trang còn khó chịu hơn cả để nguyên. */
t( '🔴 ẩn sẵn trong HTML, script mới mở ra', false !== strpos( $than, 'id="vhnb-nhac" hidden' ), $than );
t( 'và script có mở ra thật', false !== strpos( $html, 'o.hidden = false;' ) );

/* 🔴 KHÔNG PHỦ KÍN MÀN. Dải nằm ở đáy; không có lớp mờ nào phía sau, không `inset:0`. */
t( '🔴 không có lớp phủ toàn màn', false === strpos( $html, 'inset:0' )
	&& false === strpos( $html, 'width:100%;height:100%' ), '' );
t( 'neo ở đáy màn', false !== strpos( $html, 'position:fixed' )
	&& false !== strpos( $html, 'bottom:calc(12px + env(safe-area-inset-bottom' ), '' );

/* ═══════════════════════════════════════════════════════════════════ 7. VIDEO TRONG DẢI */

VHNB_Nhac::dat( array( 'bat' => true, 'dich' => 'https://khmatrix.com/cc/',
	'video' => 'https://khmatrix.com/wp-content/uploads/hd.mp4' ) );
ob_start(); VHNB_Nhac::ve(); $h2 = (string) ob_get_clean();
t( 'mp4 -> thẻ <video>', false !== strpos( $h2, '<video class="vhnb-nhac-phim"' ), $h2 );
/* 🔴 KHÔNG TỰ TẢI. Người ở cơ sở dùng 3G; 4 MB tự tải ở mỗi lượt mở trang là hết dung lượng
   của họ để đổi lấy một thứ họ không bấm. */
t( '🔴 video KHÔNG tự tải (preload="none")', false !== strpos( $h2, 'preload="none"' ), $h2 );
/* iPhone: thiếu `playsinline` là bấm phát thì nhảy sang toàn màn hình, nuốt mất cả trang. */
t( '🔴 có playsinline cho iPhone', false !== strpos( $h2, 'playsinline' ), $h2 );
/* 🔴 BẤM PHÁT THÌ DỪNG ĐẾM — không thì dải tự đóng giữa lúc đang xem, và phải tải lại trang
   mới xem tiếp được, nếu còn nhớ là có video. */
t( '🔴 bấm phát thì dừng đếm ngược', false !== strpos( $h2, "addEventListener('play'" ), $h2 );
t( 'không có nút "Xem hướng dẫn" khi đã nhúng được phim',
	false === strpos( than( $h2 ), 'vhnb-nhac-xem' ), than( $h2 ) );

VHNB_Nhac::dat( array( 'bat' => true, 'dich' => 'https://khmatrix.com/cc/',
	'video' => 'https://www.youtube.com/watch?v=abc' ) );
ob_start(); VHNB_Nhac::ve(); $h3 = (string) ob_get_clean();
t( 'YouTube -> đường dẫn, không nhúng khung bên thứ ba',
	false !== strpos( than( $h3 ), 'vhnb-nhac-xem' ) && false === strpos( $h3, '<iframe' ), than( $h3 ) );
t( 'và mở tab mới có rel="noopener"', false !== strpos( $h3, 'rel="noopener"' ), $h3 );

/* Không khai video thì không có chỗ nào nhắc tới video. */
VHNB_Nhac::dat( array( 'bat' => true, 'dich' => 'https://khmatrix.com/cc/', 'video' => '' ) );
ob_start(); VHNB_Nhac::ve(); $h4 = (string) ob_get_clean();
t( 'không có video -> không vẽ chỗ nào cho nó',
	false === strpos( than( $h4 ), '<video' ) && false === strpos( than( $h4 ), 'vhnb-nhac-xem' ), than( $h4 ) );

/* Tắt thì không in ra một byte nào — không phải in rồi giấu bằng CSS. */
VHNB_Nhac::dat( array( 'bat' => false ) );
ob_start(); VHNB_Nhac::ve(); $h5 = (string) ob_get_clean();
t( '🔴 tắt -> không in ra byte nào', '' === $h5, strlen( $h5 ) );

/* ═══════════════════════════════════════════════════════════════════ 8. DÂY NỐI */

$src_trang = file_get_contents( $bo . '/includes/class-vhnb-trang.php' );
t( 'trang Nội bộ có gọi dải', false !== strpos( $src_trang, 'VHNB_Nhac::ve()' ) );
/* ⚠️ Gác `method_exists` cùng chỗ với lời gọi — luật chung của kho (kiem-goi-cheo.php). */
t( 'lời gọi có gác method_exists',
	false !== strpos( $src_trang, "method_exists( 'VHNB_Nhac', 've' )" ) );
/* 🔴 VẼ NGAY TRƯỚC `</body>`, không vẽ ở đầu trang: chèn đầu thì nó đẩy cả nội dung xuống và
   người đang đọc bảng tin mất chỗ đang đọc. */
$i_ve  = strpos( $src_trang, 'VHNB_Nhac::ve()' );
$i_het = strpos( $src_trang, "echo '</body></html>';" );
t( '🔴 vẽ TRƯỚC </body>, không vẽ ở đầu trang',
	false !== $i_ve && false !== $i_het && $i_ve < $i_het, array( $i_ve, $i_het ) );

$src_chinh = file_get_contents( $bo . '/vhcp-noi-bo.php' );
t( 'lớp được nạp trong plugin', false !== strpos( $src_chinh, 'class-vhnb-nhac.php' ) );
/* Nạp TRƯỚC class-vhnb-trang.php — nơi gọi nó. */
t( 'nạp trước lớp gọi nó',
	strpos( $src_chinh, 'class-vhnb-nhac.php' ) < strpos( $src_chinh, 'class-vhnb-trang.php' ) );

$src_ad = file_get_contents( $bo . '/includes/class-vhnb-admin.php' );
t( 'có màn khai ở wp-admin', false !== strpos( $src_ad, 'khoi_nhac' ) );
/* 🔴 MỘT FORM, MỘT NÚT LƯU. Lồng <form> trong <form> thì trình duyệt vứt thẻ trong đi rồi gộp
   mọi ô vào form ngoài — bộ chấm công đã trả giá cho đúng lỗi này. */
t( '🔴 khối khai KHÔNG mở form riêng',
	false === strpos( substr( $src_ad, strpos( $src_ad, 'private static function khoi_nhac' ),
		(int) ( strpos( $src_ad, 'public static function ve()' )
			- strpos( $src_ad, 'private static function khoi_nhac' ) ) ), '<form' ) );
/* 🔴 MÀN KHAI PHẢI TỰ NÓI BẢN NÀO ĐANG CHẠY. Cài 1.21.0 lên hosting mà khối mới không hiện
   thì có ba khả năng — chưa bấm Thay thế, trình duyệt giữ trang cũ, OPcache giữ mã cũ — và
   cả ba nhìn từ màn hình giống hệt nhau. Một dòng số bản đọc từ CHÍNH hằng PHP đã nạp trả
   lời câu ấy bằng mắt; nó khác số ở màn Plugins, vốn đọc chú thích đầu tệp chứ không đọc mã
   đang chạy. */
t( '🔴 màn khai in ra bản đang chạy', false !== strpos( $src_ad, 'Bản đang chạy' ) );
t( '   và đọc từ hằng đã nạp, không đọc chú thích tệp',
	false !== strpos( $src_ad, "defined( 'VHNB_VERSION' ) ? VHNB_VERSION" ) );
t( '🔴 nói thẳng khi mã đang chạy CHƯA có khối này',
	false !== strpos( $src_ad, 'CHƯA CÓ' ) && false !== strpos( $src_ad, 'OPcache' ) );

/* Ô tích đọc bằng isset — đọc giá trị thì bỏ tích xong bấm Lưu là nó vẫn bật. */
t( "ô tích đọc bằng isset( \$_POST['nhac_bat'] )",
	false !== strpos( $src_ad, "isset( \$_POST['nhac_bat'] )" ) );

/* ═══════════════════════════════════════════════════════════ 9. ẨN BỚT NÚT TRÊN THANH */

require_once $bo . '/includes/class-vhnb-thanh.php';

/* Chưa khai gì thì MỌI nút đều hiện — mặc định phải là hiện, không phải ẩn. Ngược lại là mỗi
   trang mới mọc ra sau này lặng lẽ không bao giờ xuất hiện trên thanh. */
t( '🔴 chưa khai gì -> mọi nút đều hiện', VHNB_Thanh::hien( 'https://khmatrix.com/ghe/' ) );
t( 'danh sách ẩn rỗng', array() === VHNB_Thanh::ds_an(), VHNB_Thanh::ds_an() );

VHNB_Thanh::dat_an( array( 'https://khmatrix.com/ghe/', 'https://khmatrix.com/chi-phi-hn/' ) );
t( 'ẩn được một nút', ! VHNB_Thanh::hien( 'https://khmatrix.com/ghe/' ) );
t( 'nút khác không bị ảnh hưởng', VHNB_Thanh::hien( 'https://khmatrix.com/cham-cong/' ) );
t( 'ẩn hai nút thì nhớ hai', 2 === count( VHNB_Thanh::ds_an() ), VHNB_Thanh::ds_an() );

/* 🔴 CỔNG K&H KHÔNG ẨN ĐƯỢC. Nó là trang liệt kê mọi app; ẩn nốt nó thì mấy trang vừa ẩn không
   còn đường nào tới ngoài gõ tay địa chỉ — tức là nhốt người dùng trong đúng một trang. */
if ( ! class_exists( 'VHTC_Trang' ) ) {
	eval( 'class VHTC_Trang { public static function url() { return "https://khmatrix.com/cong/"; } }' );
}
t( 'nhận ra đâu là Cổng', VHNB_Thanh::la_cong( 'https://khmatrix.com/cong/' ) );
VHNB_Thanh::dat_an( array( 'https://khmatrix.com/cong/', 'https://khmatrix.com/ghe/' ) );
t( '🔴 tích ẩn Cổng thì bị BỎ QUA, Cổng vẫn hiện', VHNB_Thanh::hien( 'https://khmatrix.com/cong/' ) );
t( 'và nó không lọt vào danh sách ẩn',
	! in_array( 'https://khmatrix.com/cong/', VHNB_Thanh::ds_an(), true ), VHNB_Thanh::ds_an() );
t( 'nút kia vẫn ẩn được như thường', ! VHNB_Thanh::hien( 'https://khmatrix.com/ghe/' ) );

/* 🔴 ĐỔI ĐỊA CHỈ THÌ NÚT HIỆN LẠI, không biến mất. Thừa một nút thì nhìn thấy ngay và bỏ tích
   lại; mất một nút thì không ai biết để đi tìm. */
t( '🔴 trang đổi đường dẫn -> nút HIỆN LẠI',
	VHNB_Thanh::hien( 'https://khmatrix.com/ghe-massage/' ) );

VHNB_Thanh::dat_an( array() );
t( 'bỏ hết tích ẩn thì mọi nút hiện lại', VHNB_Thanh::hien( 'https://khmatrix.com/ghe/' ) );

/* Dây nối ở chỗ vẽ thanh và ở màn khai. */
t( 'chỗ vẽ thanh có hỏi VHNB_Thanh', false !== strpos( $src_trang, 'VHNB_Thanh::hien(' ) );
t( 'và gác method_exists cùng chỗ gọi',
	false !== strpos( $src_trang, "method_exists( 'VHNB_Thanh', 'hien' )" ) );
t( 'có màn khai nút nào hiện', false !== strpos( $src_ad, 'khoi_thanh' ) );
/* 🔴 BIỂU MẪU GỬI LÊN CÁI ĐƯỢC HIỆN, máy chủ tự suy ra cái bị ẩn. Gửi ngược lại thì trang mới
   mọc ra sau này mặc định bị ẩn — mà mặc định phải là HIỆN. */
t( '🔴 biểu mẫu gửi danh sách ĐƯỢC HIỆN, không gửi danh sách bị ẩn',
	false !== strpos( $src_ad, "name=\"thanh[]\"" )
	&& false !== strpos( $src_ad, "! in_array( (string) \$tr['url'], \$hien, true )" ) );
/* Ô tích của Cổng để `disabled`, nên trình duyệt KHÔNG gửi nó lên — phải có ô ẩn gửi thay,
   không thì mỗi lượt Lưu lại coi như Cổng "không được tích". */
t( '🔴 ô Cổng bị khoá thì có ô ẩn gửi giá trị thay',
	false !== strpos( $src_ad, 'checked disabled' )
	&& false !== strpos( $src_ad, "type=\"hidden\" name=\"thanh[]\"" ) );

echo "\n";
if ( $truot ) {
	echo 'TRƯỢT ' . count( $truot ) . ":\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — dải nhắc tự đi, nhớ khi bảo thôi, và tự chết khi hết hạn.\n";
