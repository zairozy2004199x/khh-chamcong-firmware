<?php
/**
 * KHÔNG ĐỘNG VÀO THƯ MỤC PLUGIN KHI CHƯA CHẮC XOÁ ĐƯỢC.
 *
 * ==============================================================================================
 * 🔴 SÁNG 16/09/2026 TRANG khmatrix.com/ghe SẬP — sau một lượt bấm Cập nhật ở chính trang /it.
 *    `/ghe` và `/it` cùng trả 404; phải cài tay lại bằng zip mới sống.
 *
 *    Nguyên nhân nằm sẵn trong CLAUDE.md §1, chỉ là lúc dựng nút cập nhật không ai nối hai việc:
 *      · trên host thật `includes/class-vhg-baocao.php` KẸT QUYỀN, sáu lượt cài zip không ghi đè
 *        nổi mà vẫn báo "thành công";
 *      · cơ chế bản-sao-mang-số-bản né được chuyện ấy — nhưng chỉ né lúc GHI ĐÈ;
 *      · `Plugin_Upgrader` thì XOÁ SẠCH thư mục plugin rồi mới giải nén. Một tệp không xoá được
 *        là lượt cài đứt giữa chừng, plugin chết, và mọi trang ảo (/ghe, /it, /mua-ma) 404.
 *
 *    Né được lúc ghi đè KHÔNG có nghĩa là né được lúc xoá. Bài này canh cửa soát ấy còn nguyên.
 *
 * Chạy: php tools/test/kiem-ghe-soat-xoa-truoc-khi-cai.php   (chay-het.sh tự gom)
 */
$goc = dirname( __DIR__, 2 );
$js  = (string) file_get_contents( $goc . '/vhcp-ghe/includes/class-vhg-trang.php' );
$LOI = 0; $SO = 0;
function t( $ten, $ok ) {
	global $LOI, $SO; $SO++;
	echo ( $ok ? '  ✓ ' : '  ✗ ' ) . $ten . "\n";
	if ( ! $ok ) { $LOI++; }
}

t( 'có hàm dò tệp kẹt cn_tep_ket_()', false !== strpos( $js, 'function cn_tep_ket_(' ) );
t( 'cn_chay_() có gọi nó', false !== strpos( $js, 'self::cn_tep_ket_(' ) );

/* 🔴 THỨ TỰ LÀ TẤT CẢ. Soát SAU khi upgrade() chạy thì vô nghĩa — thư mục đã bị xoá rồi. */
$vi_soat = strpos( $js, 'self::cn_tep_ket_(' );
$vi_cai  = strpos( $js, '$up->upgrade(' );
t( '🔴 soát ĐỨNG TRƯỚC $up->upgrade()', false !== $vi_soat && false !== $vi_cai && $vi_soat < $vi_cai );

/* 🔴 XOÁ TỆP CẦN QUYỀN GHI TRÊN THƯ MỤC CHA, KHÔNG PHẢI TRÊN CHÍNH TỆP.
   Soát nhầm sang is_writable($tep) là phép thử luôn xanh mà không chặn được gì: tệp chmod 444
   trong thư mục ghi được thì vẫn xoá bình thường, còn tệp chmod 777 trong thư mục khoá thì
   không. Đây đúng là chỗ dễ viết sai nhất của cả bài. */
t( '🔴 soát theo THƯ MỤC CHA: is_writable( dirname( $d ) )',
	false !== strpos( $js, 'is_writable( dirname( $d ) )' ) );
t( 'và soát cả thư mục cha của chính thư mục plugin',
	false !== strpos( $js, 'is_writable( dirname( $thu_muc ) )' ) );

/* Chỉ đúng khi WordPress ghi trực tiếp; host đòi FTP thì is_writable() không nói lên gì. */
t( 'chỉ soát khi phương thức ghi là direct',
	false !== strpos( $js, "'direct' === get_filesystem_method()" ) );

/* Câu báo phải nói rõ CHƯA làm gì — người đọc cần biết trang vẫn đang sống. */
t( '🔴 câu báo nói rõ CHƯA CÀI GÌ CẢ', false !== strpos( $js, 'CHƯA CÀI GÌ CẢ' ) );
t( 'và chỉ đường cài tay bằng zip', false !== strpos( $js, 'Plugin → Tải lên' ) );

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * CHẠY CHÍNH HÀM THẬT TRÊN THƯ MỤC THẬT.
 *
 * Mọi phép trên mới chỉ soát chữ trong tệp — chúng bắt được chuyện "ai đó gỡ cửa soát đi", nhưng
 * KHÔNG bắt được chuyện cửa soát có hoạt động hay không. Mà đây đúng là loại hàm dễ viết sai một
 * cách im lặng: trả về mảng rỗng thì nhìn y hệt "thư mục sạch, cài được".
 *
 * Nên bốc nguyên thân hàm ra khỏi tệp nguồn rồi chạy — KHÔNG chép lại luật vào đây (§6 CLAUDE.md:
 * luật chép thành bản sao thứ hai là bản sao không bao giờ được vá).
 * ══════════════════════════════════════════════════════════════════════════════════════════════ */
echo "── Chạy thật ──\n";
if ( ! preg_match( '/private static function cn_tep_ket_\( \$thu_muc \) \{.*?\n\t\}/s', $js, $mh ) ) {
	t( 'bốc được thân hàm cn_tep_ket_() ra để chạy', false );
} else {
	/* Hàm thật gọi một tiện ích của WordPress; dựng bản tối giản đúng nghĩa của nó để chạy ngoài
	   WP. Chỉ là dấu gạch chéo cuối — không phải luật đang thử, nên dựng ở đây không vi phạm §6. */
	if ( ! function_exists( 'trailingslashit' ) ) {
		function trailingslashit( $x ) { return rtrim( (string) $x, '/\\' ) . '/'; }
	}
	eval( 'class BeKiem { public static ' . substr( $mh[0], strlen( 'private static ' ) ) . ' }' );

	$tmp = sys_get_temp_dir() . '/kiem-xoa-' . getmypid();
	@mkdir( $tmp . '/bo/includes', 0777, true );
	file_put_contents( $tmp . '/bo/bo.php', '<?php' );
	file_put_contents( $tmp . '/bo/includes/lop.php', '<?php' );

	t( 'thư mục ghi được → không báo gì', array() === BeKiem::cn_tep_ket_( $tmp . '/bo' ) );

	/* Tệp CHỈ ĐỌC trong thư mục ghi được thì VẪN XOÁ ĐƯỢC — không được báo. Đây là ca mà một bài
	   thử viết theo is_writable($tep) sẽ kêu oan, và kêu oan thì người ta tắt cửa soát đi. */
	@chmod( $tmp . '/bo/includes/lop.php', 0444 );
	t( '🔴 tệp chmod 444 trong thư mục ghi được → VẪN không báo (xoá được)',
		array() === BeKiem::cn_tep_ket_( $tmp . '/bo' ) );
	@chmod( $tmp . '/bo/includes/lop.php', 0644 );

	/* 🔴 Thư mục KHOÁ → tệp bên trong không xoá được → phải báo. Đúng ca đã làm sập trang 16/09.
	   ⚠️ CHẠY BẰNG ROOT THÌ BỎ QUA, KHÔNG ĐỎ. root ghi được mọi chỗ nên `is_writable()` trả TRUE
	      cả trên thư mục 0555 — phép này không đo được gì, và để nó đỏ vì môi trường là đường
	      dẫn thẳng tới chỗ bị tắt đi (cùng lý lẽ với "không có origin thì bỏ qua" ở
	      kiem-so-ban-doc-nhat.php). Nói thẳng ra là đã bỏ qua, đừng im lặng tính là đạt. */
	if ( function_exists( 'posix_getuid' ) && 0 === posix_getuid() ) {
		echo "  ~ bỏ qua phép 'thư mục khoá' — đang chạy bằng root, is_writable() luôn TRUE\n";
	} else {
		@chmod( $tmp . '/bo/includes', 0555 );
		clearstatcache();
		$ra = BeKiem::cn_tep_ket_( $tmp . '/bo' );
		$bat = false;
		foreach ( (array) $ra as $x ) { if ( false !== strpos( $x, 'lop.php' ) ) { $bat = true; } }
		t( '🔴 thư mục khoá 0555 → BÁO đúng tệp bên trong', $bat );
		@chmod( $tmp . '/bo/includes', 0755 );
	}

	t( 'thư mục không tồn tại → mảng rỗng, không nổ', array() === BeKiem::cn_tep_ket_( $tmp . '/khong-co' ) );

	@unlink( $tmp . '/bo/includes/lop.php' ); @unlink( $tmp . '/bo/bo.php' );
	@rmdir( $tmp . '/bo/includes' ); @rmdir( $tmp . '/bo' ); @rmdir( $tmp );
}

echo "\n" . ( $LOI ? "ĐỎ: $LOI/$SO phép hỏng" : "SẠCH: $SO phép" ) . "\n";
exit( $LOI ? 1 : 0 );
