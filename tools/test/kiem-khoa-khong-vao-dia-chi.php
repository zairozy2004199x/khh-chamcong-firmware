<?php
/**
 * KHOÁ GITHUB KHÔNG ĐƯỢC NẰM TRONG ĐỊA CHỈ — chốt cho một lần lộ khoá THẬT.
 *
 * =============================================================================================
 * Anh Thắng 16/09/2026 gửi ảnh hộp thoại trên khmatrix.com: *"Download failed. Địa chỉ URL không
 * hợp lệ"* — và trong chính câu báo lỗi ấy là NGUYÊN CÁI KHOÁ GitHub của anh, in ra màn hình,
 * rồi đi vào ảnh chụp, rồi đi vào chat. Khoá phải thu hồi.
 *
 * Nguyên nhân: cả MƯỜI BA bộ tự cập nhật đều nhét khoá vào phần "người dùng" của địa chỉ
 * (`https://<khoá>@api.github.com/…`). Hai hậu quả cùng lúc:
 *
 *   1. KHÔNG TẢI ĐƯỢC BAO GIỜ. `wp_http_validate_url()` của WordPress chối mọi địa chỉ có
 *      `user@host` — chốt chống SSRF. Nên đường tải ấy chưa từng chạy.
 *   2. LỘ KHOÁ. Chú thích trong mã có dặn "không log nó, không in nó ra màn hình" — nhưng người
 *      in ra là WordPress, không phải mã của mình.
 *
 * 🔴 VÌ SAO PHẢI LÀ BÀI KIỂM, KHÔNG PHẢI MỘT LỜI DẶN. Lời dặn đã có sẵn trong mã, ngay trên
 *    dòng gây lỗi, và nó không ngăn được gì cả — vì nó dặn sai người. Một phép thử thì không
 *    dặn ai; nó chặn.
 *
 * ⚠️ QUÉT CẢ MƯỜI BA BỘ, không chỉ bộ đang sửa. Lớp này được chép tay từ plugin này sang plugin
 *    khác; vá một bộ rồi quên mười hai bộ kia là khoá vẫn lộ qua đúng đường ấy.
 *
 * Chạy: php tools/test/kiem-khoa-khong-vao-dia-chi.php
 */

$goc = dirname( __DIR__, 2 );
$LOI = 0; $SO = 0;
function t( $ten, $ok, $them = '' ) {
	global $LOI, $SO; $SO++;
	echo ( $ok ? '  ✓ ' : '  ✗ ' ) . $ten . ( $ok || '' === $them ? '' : ' — ' . $them ) . "\n";
	if ( ! $ok ) { $LOI++; }
}

/* Mọi bộ tự cập nhật trong kho — nhận bằng TÊN TỆP, không gõ tay danh sách. Thêm plugin mới là
   nó tự vào diện soát, khỏi phải nhớ. */
$ds = glob( $goc . '/wordpress/*/includes/*tu-cap-nhat.php' );
sort( $ds );
echo "Soát " . count( $ds ) . " bộ tự cập nhật\n";
t( 'tìm thấy ít nhất 10 bộ (đề phòng đường dẫn đổi mà bài kiểm im lặng bỏ qua hết)',
	count( $ds ) >= 10, count( $ds ) . ' bộ' );

foreach ( $ds as $tep ) {
	$ten = basename( dirname( dirname( $tep ) ) );
	$src = (string) file_get_contents( $tep );
	/* Bỏ chú thích trước khi soi: khối chú thích của bản vá có NHẮC TỚI cách làm sai, và bắt
	   đúng câu nhắc ấy là bài kiểm kêu oan ở chính chỗ vừa sửa đúng. */
	$ma = preg_replace( '#/\*.*?\*/#s', '', $src );
	/* 🔴 `(?<!:)` LÀ CẢ CÁI BÀI KIỂM NÀY SỐNG HAY CHẾT.
	   Bản đầu của em bỏ chú thích `//` bằng `#//[^\n]*#` — mà `'https://'` CÓ hai dấu gạch ấy,
	   nên phép bỏ chú thích xoá luôn từ `https://` tới hết dòng, tức xoá đúng dòng cần soi. Bài
	   kiểm xanh mướt 40/40 trong khi mã đang hỏng thật. Chỉ lượt PHÁ THỬ mới lộ ra: trả một bộ
	   về cách cũ mà không phép nào đỏ.
	   Bài học: một phép thử chưa phá thử thì chưa biết nó có canh gì không. */
	$ma = preg_replace( '#(?<!:)//[^\n]*#', '', $ma );

	t( $ten . ': KHÔNG ghép khoá vào địa chỉ bằng dấu @',
		false === strpos( $ma, "rawurlencode( \$khoa ) . '@'" )
		&& false === strpos( $ma, "rawurlencode( \$key ) . '@'" ), 'còn ghép khoá vào địa chỉ' );

	/* Chốt rộng hơn: không một chỗ nào dựng chuỗi `https://` + biến + `@`. */
	t( $ten . ': không dựng địa chỉ dạng https://<gì đó>@',
		0 === preg_match( "#'https://'\s*\.\s*\\\$[A-Za-z_]+\s*\.\s*'@#", $ma ), 'dựng địa chỉ có phần người dùng' );

	/* Và phải có đường đúng: khoá đi ở tiêu đề. */
	t( $ten . ': có gắn khoá vào tiêu đề Authorization',
		false !== strpos( $ma, "'Authorization'" ), 'không thấy tiêu đề Authorization' );
}

echo "\n";
if ( $LOI ) { echo "✗ TRƯỢT $LOI / $SO\n"; exit( 1 ); }
echo "✓ ĐẠT — $SO phép: không bộ nào để khoá GitHub lọt vào địa chỉ.\n";
