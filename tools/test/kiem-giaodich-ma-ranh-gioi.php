<?php
/**
 * TỰ NHẬN NHÃN CHO GIAO DỊCH: MÃ NỘP TIỀN PHẢI KHỚP TRỌN, KHÔNG ĐƯỢC KHỚP LỒNG NHAU.
 *
 * Anh Thắng 26/09/2026, ảnh màn Sao Kê "Nhãn phân loại": trong cùng một số tài khoản, một số dòng
 * hiện huy hiệu xanh "🅐 tự: TÀU TÂN AN", một số dòng thì không (dropdown vẫn chọn đúng TÀU TÂN AN
 * cho cả hai) — *"Sao lúc có lúc không, lưu tk là tự dò luôn chứ"*. Nhiều khả năng nhất cho ĐÚNG
 * ca này: những dòng không có huy hiệu đã được người xác nhận tay từ trước (badge chỉ hiện khi
 * CHƯA ai xác nhận — xem `!r.nhan && r.coSoMa` trong app.html), không phải một lỗi.
 *
 * Nhưng nhân lúc soi `rpc_getGiaoDich()`, lộ ra một lỗi THẬT KHÁC, cùng họ với lỗi cũ bên
 * khh-doanh-thu (`khh_dt_doan_co_so()`, đã vá: "KH705MTDMN0002 nằm gọn bên trong KH705MTDMN0020"):
 * hàm tự nhận nhãn gộp MỌI mã đã khai thành một regex alternation (`implode('|', $maList)`) rồi
 * `preg_match` một lần, KHÔNG RANH GIỚI hai đầu. Hai mã nộp tiền chỉ chênh nhau một chữ số
 * (`KH705KVCMN0005` và `KH705KVCMN00050`) là một mã nằm GỌN trong mã kia; mã NGẮN đứng trước trong
 * mảng (đến từ thứ tự khai, không cố định) sẽ khớp trước rồi DỪNG, cắt cụt — tiền của cơ sở mang
 * mã dài âm thầm bị gán nhãn tự động sang cơ sở mang mã ngắn. Vá cho chắc, dù không phải nguyên
 * nhân của đúng ảnh anh gửi.
 *
 * Bài này không dựng cả lớp `SAOKE_App` (quá nhiều phụ thuộc — sổ Sheet, PIN, cấu hình cổng…) mà
 * khoá đúng CÁCH DỰNG REGEX, lấy nguyên dòng mã nguồn ra chạy thật.
 *
 * Chạy: php tools/test/kiem-giaodich-ma-ranh-gioi.php
 */
$goc = dirname( __DIR__, 2 );
$s   = (string) file_get_contents( $goc . '/vhcp-saoke/vhcp-saoke.php' );
$dat = 0; $hong = array();
function k( $ten, $ok ) { global $dat, $hong; if ( $ok ) { $dat++; } else { $hong[] = $ten; } }

/* Lấy nguyên dòng dựng $maRe từ mã nguồn thật — sai một ký tự trong dòng ấy là bài này đỏ ngay,
   không phải chép lại logic rồi tự lừa mình rằng đã thử đúng bản đang chạy. */
if ( ! preg_match( "/if \\( \\\$maList \\) \\{ \\\$maRe = (.+?); \\}/", $s, $mm ) ) {
	echo "KHÔNG tìm thấy dòng dựng \$maRe trong vhcp-saoke.php\n";
	exit( 1 );
}
$dongDungMaRe = $mm[1];   // biểu thức PHP nguyên văn: '/(?<!...)(' . implode( '|', $maList ) . ')(?!...)/i'
k( '🔴 dòng dựng $maRe có ranh giới hai đầu (?<![A-Za-z0-9]) … (?![A-Za-z0-9])',
	false !== strpos( $dongDungMaRe, '(?<![A-Za-z0-9])' ) && false !== strpos( $dongDungMaRe, '(?![A-Za-z0-9])' ) );

/* Chạy THẬT đúng biểu thức lấy từ mã nguồn — gán $maList rồi eval chính dòng ấy, không chép lại
   logic bằng tay (chép tay là tự thử một bản khác, không phải bản đang chạy thật). */
function dung_ma_re( $ma_list ) {
	global $dongDungMaRe;
	$maList = $ma_list;
	return eval( 'return ' . $dongDungMaRe . ';' );
}

/* Ca thật đứng sau lời giải thích trong mã nguồn: "KH705KVCMN0005 nằm gọn bên trong
   KH705KVCMN00050" — mã NGẮN (Tân An) đứng TRƯỚC mã DÀI HƠN (cơ sở khác) một chữ số trong mảng.
   Alternation `preg_match` không có cờ toàn cục: tại một vị trí, PCRE thử các nhánh THEO THỨ TỰ
   VIẾT TRONG PATTERN và dừng ở nhánh ĐẦU TIÊN khớp được — mã ngắn đứng trước, lại đúng là một TIỀN
   TỐ của mã dài, nên khớp trước và cắt cụt, dù nội dung thật mang mã DÀI của cơ sở khác. */
$re = dung_ma_re( array( 'KH705KVCMN0005', 'KH705KVCMN00050' ) );   // mã ngắn đứng TRƯỚC
$nd_ma_dai = 'NHAN TU 18865471 TRACE 902045 ND KH705KVCMN00050-190826-02:23:48 6231ASCB02ZDULSL';
preg_match( $re, $nd_ma_dai, $m1 );
k( '🔴 nội dung mang mã DÀI (KH705KVCMN00050, cơ sở khác) phải khớp TRỌN mã dài, không bị mã ngắn đứng trước cắt cụt',
	isset( $m1[1] ) && 'KH705KVCMN00050' === $m1[1] );

/* Đối chứng: KHÔNG có ranh giới thì đúng ca trên vỡ — mã ngắn "KH705KVCMN0005" (đứng trước trong
   mảng, và là tiền tố của mã dài) khớp trước rồi dừng, cắt cụt mất "0" cuối — tiền của cơ sở kia
   bị gán nhầm sang Tân An. Chứng minh ranh giới ở TRÊN là thứ ĐANG NGĂN đúng lỗi này. */
$re_khong_ranh = '/(' . implode( '|', array_map( function ( $m ) { return preg_quote( $m, '/' ); }, array( 'KH705KVCMN0005', 'KH705KVCMN00050' ) ) ) . ')/i';
preg_match( $re_khong_ranh, $nd_ma_dai, $mk );
k( 'không có ranh giới thì đúng ca trên khớp NHẦM mã ngắn bị cắt cụt (chứng minh ranh giới ở trên có tác dụng thật)',
	isset( $mk[1] ) && 'KH705KVCMN0005' === $mk[1] );

/* Mã vẫn phải khớp bình thường khi đứng liền dấu câu thường gặp trong nội dung sao kê thật (gạch
   ngang, khoảng trắng) — ranh giới không được siết tới mức làm mù ca thật. */
$re2 = dung_ma_re( array( 'KH705KVCMN0005' ) );
foreach ( array(
	'... ND KH705KVCMN0005-210926-00:26:34 ...' => true,
	'NHAN TU 18865471 TRACE 167362 ND KH705KVCMN0005-110926-01:26:08 ...' => true,
	'KH705KVCMN0005' => true,
) as $nd => $phai_khop ) {
	k( 'mã đứng cạnh dấu câu thường gặp vẫn khớp: "' . substr( $nd, 0, 40 ) . '…"',
		1 === preg_match( $re2, $nd ) );
}
/* Và KHÔNG được khớp khi mã chỉ là một phần của một chuỗi số dài hơn (ca ngược: mã NGẮN được khai,
   nội dung mang một mã KHÁC dài hơn chứa nó — không phải chuyện hoang đường, vài site đặt trùng
   4 số cuối cho nhiều cơ sở). */
$re3 = dung_ma_re( array( '0005' ) );
k( '🔴 mã ngắn "0005" KHÔNG được khớp nhầm khi nó chỉ là đuôi của một mã dài hơn trong nội dung',
	0 === preg_match( $re3, 'ND KH705KVCMN0005-210926' ) );

echo "\n" . ( $hong ? 'ĐỎ ' . count( $hong ) . '/' . ( $dat + count( $hong ) ) . " phép:\n" . implode( "\n", array_map( function ( $t ) { return "  ✗ $t"; }, $hong ) ) . "\n" : "✓ SẠCH — $dat phép: mã nộp tiền khớp có ranh giới, không còn lệ thuộc thứ tự khai.\n" );
exit( $hong ? 1 : 0 );
