<?php
/**
 * MẤY THƯ MỤC PLUGIN LÀ BẢN CHỤP CŨ PHẢI ĐƯỢC ĐÁNH DẤU.
 *
 * =================================================================================================
 * 🔴 CHUYỆN ĐÃ XẢY RA, 19/09/2026
 * =================================================================================================
 * Em vá đường nối danh tính lên `wordpress/vhcp-chi-phi*` rồi đóng gói gửi anh Thắng. Anh mở lên
 * cài thì WordPress báo:
 *
 *     Hiện tại 1.212.0    ·    Đã tải lên 1.188.0
 *
 * Bấm "Thay thế" là LÙI plugin về mã của mấy tháng trước — mất trắng mọi thứ làm giữa chừng.
 * Anh Thắng nhìn ra kịp, em thì không.
 *
 * Mấy thư mục ấy nằm trong kho từ lần gói chung `1c781a5` và đứng im từ đó, trong khi bản chạy
 * thật ngoài host đã đi xa hơn nhiều chục phiên bản. KHÔNG CÓ GÌ trong kho nói ra điều đó:
 *   · số phiên bản trong thư mục vẫn "hợp lệ" (header khớp hằng số) nên `kiem-phien-ban.py` xanh;
 *   · `tools/build-plugin-zip.sh` vẫn gói được, không hỏi một câu nào;
 *   · và bản gói ra trông y như một bản cài thật.
 *
 * Bài này là cái mốc duy nhất đứng giữa lần sau và đúng cái bẫy ấy.
 *
 * ⚠️ BÀI NÀY KHÔNG CANH MÃ, NÓ CANH MỘT LỜI CẢNH BÁO. Nghe yếu, nhưng thứ đã hỏng lần trước
 *    chính là KHÔNG AI BIẾT — chứ không phải mã sai.
 *
 * Chạy: php tools/test/kiem-nguon-cu.php
 */

$goc = dirname( dirname( __DIR__ ) );

/* ═════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 DANH SÁCH NGƯỢC: KHAI THỨ ĐƯỢC NUÔI Ở ĐÂY, KHÔNG KHAI THỨ KHÔNG ĐƯỢC NUÔI
 * ═════════════════════════════════════════════════════════════════════════════════════════════
 * Bản đầu của bài này gõ tay bốn thư mục chi phí — đúng bốn cái em vừa vá. Sai kiểu quen thuộc:
 * hôm sau anh Thắng hỏi *"còn báo cáo Fabi nữa"*, và `khh-doanh-thu` cũng là bản chụp cũ y như
 * thế, mà bài thử thì xanh vì nó không có trong danh sách.
 *
 * Soi lại cả kho thì ra: **chỉ `vhcp-cham-cong` được nuôi ở đây** (75 commit). Mười ba thư mục
 * còn lại đều 1–5 commit, phần lớn chỉ có đúng lần gói chung `1c781a5`.
 *
 * Nên khai NGƯỢC: kể tên thứ được nuôi, còn lại đều phải có mốc. Thêm một bản chụp mới vào kho
 * mà quên đánh dấu là bài này đỏ ngay — chứ không phải chờ tới lúc có người gói nhầm rồi gửi đi.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
/* ⚠️ 22/09/2026 — nhánh `claude/doc-lai-wed-ban-ve-lvticf` nuôi thêm BA plugin: JP Capsule
   (555 commit, không nhánh nào có bản mới hơn 1.18.0), Vận Hành cơ sở và Nhà Ma — cả ba chỉ
   tồn tại ở nhánh ấy. Dán mốc "đừng đóng gói" lên chúng là NÓI DỐI: bản gói từ đây chính là bản
   thật đang cài ngoài host. Bài này canh một lời cảnh báo, và lời cảnh báo sai còn tệ hơn không
   có — người ta sẽ thôi đọc mọi mốc còn lại. */
$NUOI_O_DAY = array( 'vhcp-cham-cong', 'vhcp-jp', 'vhcp-van-hanh', 'vhcp-nha-ma' );
const MOC = 'KHONG-PHAI-NGUON-THAT.md';

$BAN_CHUP = array();
foreach ( (array) scandir( $goc . '/wordpress' ) as $x ) {
	if ( '.' === $x || '..' === $x ) { continue; }
	if ( ! is_dir( $goc . '/wordpress/' . $x ) ) { continue; }
	if ( in_array( $x, $NUOI_O_DAY, true ) ) { continue; }
	$BAN_CHUP[] = $x;
}
sort( $BAN_CHUP );

$dat = 0; $truot = array();
function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( null === $them ? '' : "\n      → " . (string) $them );
}

echo "— bản chụp cũ phải có mốc cảnh báo —\n";
/* Không tìm ra thư mục nào thì bài này đang chạy đúng ngần ấy vòng lặp rồi báo "SẠCH" — cùng
   thứ nó sẽ làm khi đường dẫn sai. Đếm trước. */
t( 'tìm thấy thư mục để soi', count( $BAN_CHUP ) >= 10, count( $BAN_CHUP ) . ' thư mục' );
t( '🔴 `vhcp-cham-cong` KHÔNG bị kể là bản chụp — nó là thứ kho này thật sự nuôi',
	! in_array( 'vhcp-cham-cong', $BAN_CHUP, true ) );
foreach ( $BAN_CHUP as $d ) {
	$thu_muc = $goc . '/wordpress/' . $d;
	if ( ! is_dir( $thu_muc ) ) { continue; }   // gỡ hẳn khỏi kho thì càng tốt

	$f = $thu_muc . '/' . MOC;
	t( $d . ': có mốc ' . MOC, file_exists( $f ), 'thiếu tệp mốc' );
	if ( ! file_exists( $f ) ) { continue; }

	$n = (string) file_get_contents( $f );
	/* Mốc phải NÓI RA hai điều, không chỉ tồn tại: đừng đóng gói, và nguồn thật ở đâu. */
	t( $d . ': mốc nói rõ ĐỪNG ĐÓNG GÓI', false !== mb_strpos( $n, 'ĐỪNG ĐÓNG GÓI' ), 'mốc rỗng nghĩa' );
	t( $d . ': mốc chỉ ra phải vá lên nguồn thật',
		false !== mb_strpos( $n, 'nguồn thật' ), 'mốc không chỉ đường' );
	/* ⚠️ MỐC PHẢI GHI SỐ BẢN ĐANG NẰM TRONG KHO. Không có con số ấy thì người đọc vẫn không
	   biết mình đang cầm bản bao nhiêu để mà đối chiếu với bản đang chạy — và đó chính là
	   phép so đã cứu vụ 19/09 (1.212.0 so với 1.188.0). */
	$chinh = $thu_muc . '/' . $d . '.php';
	if ( file_exists( $chinh ) ) {
		$m_v = array();
		if ( preg_match( '/^\s*\*\s*Version:\s*([0-9][0-9.]*)/m',
			(string) file_get_contents( $chinh ), $m_v ) ) {
			t( $d . ': mốc ghi đúng số bản trong kho (' . $m_v[1] . ')',
				false !== strpos( $n, $m_v[1] ), 'mốc không có số bản' );
		}
	}
}

/* 🔴 VÀ MỐC PHẢI ĐI VÀO BẢN GÓI. `build-plugin-zip.sh` có thể lọc bớt tệp; lọc mất cái mốc thì
   người mở zip ra vẫn không thấy cảnh báo nào — mà đó đúng là người sắp bấm "Thay thế". */
echo "— mốc phải nằm trong bản gói —\n";
$sh = $goc . '/tools/build-plugin-zip.sh';
if ( file_exists( $sh ) ) {
	$m = (string) file_get_contents( $sh );
	t( 'script gói KHÔNG loại bỏ tệp .md',
		false === strpos( $m, "-x '*.md'" ) && false === strpos( $m, '*.md' ), 'script đang lọc .md' );
}

echo "\n";
if ( $truot ) {
	echo '🔴 HỎNG ' . count( $truot ) . " phép thử:\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "✓ ĐẠT: $dat phép thử — bản chụp cũ đều có mốc, không ai gói nhầm nữa.\n";
