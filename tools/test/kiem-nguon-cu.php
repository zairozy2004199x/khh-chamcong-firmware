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

/* Thư mục nuôi ở NƠI KHÁC. Thêm plugin vào đây khi phát hiện thêm một bản chụp cũ. */
$BAN_CHUP = array(
	'vhcp-chi-phi', 'vhcp-chi-phi-hn', 'vhcp-chi-phi-mtd', 'vhcp-chi-phi-vp',
);
const MOC = 'KHONG-PHAI-NGUON-THAT.md';

$dat = 0; $truot = array();
function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( null === $them ? '' : "\n      → " . (string) $them );
}

echo "— bản chụp cũ phải có mốc cảnh báo —\n";
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
