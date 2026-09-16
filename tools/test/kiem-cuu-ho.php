<?php
/**
 * PLUGIN CỨU HỘ PHẢI SỐNG ĐƯỢC KHI MỌI THỨ KHÁC ĐÃ CHẾT.
 *
 * ==============================================================================================
 * 🔴 SÁNG 16/09/2026 khmatrix.com/ghe SẬP giữa một lượt cập nhật, và bài học đắt nhất hôm ấy là:
 *    NÚT CỨU HỘ NẰM TRONG THỨ ĐANG HỎNG THÌ KHÔNG PHẢI NÚT CỨU HỘ. Trang /it nằm trong chính
 *    plugin Ghế; Ghế chết thì /it chết theo, đúng lúc cần nó nhất.
 *
 * Bài này canh những tính chất làm nên "sống độc lập". Mỗi phép dưới đây tương ứng một cách mà
 * tệp cứu hộ có thể lặng lẽ mất khả năng cứu:
 *
 * Chạy: php tools/test/kiem-cuu-ho.php   (chay-het.sh tự gom)
 */
$goc = dirname( __DIR__, 2 );
$thu = $goc . '/vhcp-cuu-ho';
$s   = is_file( $thu . '/vhcp-cuu-ho.php' ) ? (string) file_get_contents( $thu . '/vhcp-cuu-ho.php' ) : '';
$ghe = (string) file_get_contents( $goc . '/vhcp-ghe/includes/class-vhg-trang.php' );
$LOI = 0; $SO = 0;
function t( $ten, $ok ) {
	global $LOI, $SO; $SO++;
	echo ( $ok ? '  ✓ ' : '  ✗ ' ) . $ten . "\n";
	if ( ! $ok ) { $LOI++; }
}

/**
 * MÃ THẬT, BỎ CHÚ THÍCH.
 *
 * ⚠️ Bản đầu của bài này dò chuỗi trên NGUYÊN tệp, nên hai phép quan trọng nhất đỏ oan: chú
 *    thích có nhắc `VHG_Trang::cn_tep_ket_()` và `upgrader_process_complete` để GIẢI THÍCH vì
 *    sao không dùng chúng. Bài kiểm kêu oan là bài người ta tắt đi — mà tắt rồi thì lần sau ai
 *    thật sự gọi sang plugin khác cũng không bị bắt. Nên soi mã đã bỏ chú thích.
 */
function chi_ma( $php ) {
	$ra = '';
	foreach ( token_get_all( $php ) as $t ) {
		if ( is_array( $t ) ) {
			if ( in_array( $t[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) { continue; }
			$ra .= $t[1];
		} else { $ra .= $t; }
	}
	return $ra;
}
$sm = '' !== $s ? chi_ma( $s ) : '';

echo "── Sống độc lập ──\n";
t( 'có tệp vhcp-cuu-ho/vhcp-cuu-ho.php', '' !== $s );

/* 🔴 ĐÚNG MỘT TỆP. Thư mục nhiều tệp là thứ có thể cài dở dang — mà cài dở dang chính là cái
   plugin này đi chữa. Một tệp thì hoặc có hoặc không, không có trạng thái ở giữa. */
$ds = array_values( array_filter( (array) glob( $thu . '/*' ), 'is_file' ) );
t( '🔴 thư mục chỉ có ĐÚNG 1 tệp (đang có ' . count( $ds ) . ')', 1 === count( $ds ) );
t( 'không có thư mục con nào', ! array_filter( (array) glob( $thu . '/*' ), 'is_dir' ) );

/* 🔴 KHÔNG ĐƯỢC PHỤ THUỘC PLUGIN KHÁC. Gọi một lớp của Ghế là tự trói mình vào thứ mình đi cứu. */
foreach ( array( 'VHG_', 'VHCC_', 'SAOKE_', 'VHCP_' ) as $tien_to ) {
	t( "không GỌI lớp $tien_to* của plugin khác", false === strpos( $sm, $tien_to ) );
}

/* 🔴 KHÔNG DÙNG LUẬT ĐƯỜNG DẪN. Trang ảo sống nhờ luật nằm trong CSDL; lượt cài hỏng làm mất
   luật ấy là trang cứu hộ 404 đúng hôm cần. Mở bằng tham số truy vấn thì không cần luật nào. */
t( '🔴 không khai add_rewrite_rule', false === strpos( $s, 'add_rewrite_rule' ) );
t( 'mở bằng tham số truy vấn trên trang chủ', false !== strpos( $s, "const THAM_SO = 'cuuho';" )
	&& false !== strpos( $s, '$_GET[ self::THAM_SO ]' ) );

echo "── Ảnh chụp ──\n";
/* 🔴 CHỤP TRƯỚC KHI XOÁ. `upgrader_process_complete` là đã muộn — lúc ấy bản cũ không còn trên
   đĩa để mà chụp. */
t( "🔴 móc vào upgrader_pre_install (trước), KHÔNG phải upgrader_process_complete (sau)",
	false !== strpos( $sm, "add_filter( 'upgrader_pre_install'" )
	&& false === strpos( $sm, 'upgrader_process_complete' ) );

/* 🔴 LÀ BỘ LỌC NẰM GIỮA LUỒNG CẬP NHẬT: trả về WP_Error hay false là CHẶN lượt cài. Một tệp cứu
   hộ mà chụp hỏng rồi chặn luôn lượt cập nhật thì nó là thứ gây sự cố. Mọi đường ra phải trả
   `$tra` nguyên vẹn. */
if ( preg_match( '/function chup_truoc\( \$tra, \$dau \).*?\n\t\}/s', $s, $mc ) ) {
	$ra = preg_match_all( '/\breturn\b[^;]*;/', $mc[0], $mr );
	$xau = 0;
	foreach ( $mr[0] as $r ) { if ( 'return $tra;' !== trim( $r ) ) { $xau++; } }
	t( "🔴 chup_truoc(): mọi lệnh return đều trả \$tra nguyên vẹn ($ra lệnh, $xau lệnh khác)",
		$ra > 0 && 0 === $xau );
} else {
	t( 'đọc được thân chup_truoc()', false );
}

/* Kho ảnh phải ở uploads/: cài đè plugin là WordPress xoá sạch thư mục plugin, ảnh để trong đó
   thì mất đúng vào lượt cần nó. */
t( '🔴 kho ảnh nằm trong uploads/, không nằm trong thư mục plugin',
	false !== strpos( $s, 'wp_upload_dir()' ) && false !== strpos( $s, "'vhcp-cuu-ho'" ) );
t( 'chặn đọc kho từ web (.htaccess + index.php)',
	false !== strpos( $s, "'/.htaccess'" ) && false !== strpos( $s, "'/index.php'" ) );
t( 'có dọn kho, không để phình mãi', false !== strpos( $s, 'function don_kho(' ) );

echo "── Hạ cấp ──\n";
t( 'soát xoá được TRƯỚC khi cài', false !== strpos( $s, 'self::tep_ket(' ) );
$vi_soat = strpos( $s, 'self::tep_ket(' );
$vi_cai  = strpos( $s, '$up->install(' );
t( '🔴 soát đứng TRƯỚC $up->install()', false !== $vi_soat && false !== $vi_cai && $vi_soat < $vi_cai );
t( 'cài xong thì BẬT lại plugin nếu đang tắt', false !== strpos( $s, 'activate_plugin( $duong )' ) );
t( '🔴 và nạp lại luật đường dẫn (không thì trang ảo vẫn 404)',
	false !== strpos( $s, 'flush_rewrite_rules( false )' ) );

echo "── Cửa vào ──\n";
t( '🔴 mã cứu hộ lưu dạng BĂM, không lưu mã trần (CLAUDE.md §4)',
	false !== strpos( $s, 'wp_hash_password(' ) && false !== strpos( $s, 'wp_check_password(' ) );
t( 'không có mã mặc định — chưa đặt thì chỉ đường đăng nhập chạy',
	false !== strpos( $s, 'if ( ! self::co_ma() || \'\' === $ma_go ) { return false; }' ) );
t( 'có khoá tạm khi gõ sai nhiều lần', false !== strpos( $s, 'set_transient( $khoa' ) );
t( 'đường đăng nhập đòi quyền update_plugins', false !== strpos( $s, "current_user_can( 'update_plugins' )" ) );

echo "── Hai bản sao của luật 'xoá được không' ──\n";
/* ⚠️ Luật này có HAI bản: một ở đây, một ở VHG_Trang::cn_tep_ket_(). CLAUDE.md §6 cấm chép luật,
   và ngoại lệ này CỐ Ý: cả lý do tồn tại của tệp cứu hộ là chạy được khi Ghế đã chết, nên nó
   không được gọi sang Ghế. Đổi lại, bài thử phải canh hai bên nói CÙNG một điều — bản sao được
   tha thì cũng là bản sao không ai vá. */
foreach ( array( 'vhcp-cuu-ho' => $s, 'vhcp-ghe' => $ghe ) as $ten => $mã ) {
	t( "$ten: soát theo THƯ MỤC CHA — is_writable( dirname( \$d ) )",
		false !== strpos( $mã, 'is_writable( dirname( $d ) )' ) );
	t( "$ten: soát cả thư mục cha của chính thư mục plugin",
		false !== strpos( $mã, 'is_writable( dirname( $thu_muc ) )' ) );
	t( "$ten: chỉ soát khi phương thức ghi là direct",
		false !== strpos( $mã, "'direct' === get_filesystem_method()" ) );
}

echo "── Số bản ──\n";
preg_match( '/^ \* Version:\s+([0-9][0-9.]*)/m', $s, $mh );
preg_match( "/define\( 'VHCH_VERSION', '([0-9][0-9.]*)' \)/", $s, $mv );
$h = isset( $mh[1] ) ? $mh[1] : '?';
$v = isset( $mv[1] ) ? $mv[1] : '?';
t( "🔴 header == VHCH_VERSION ($h vs $v)", '?' !== $h && $h === $v );

echo "\n" . ( $LOI ? "ĐỎ: $LOI/$SO phép hỏng" : "SẠCH: $SO phép" ) . "\n";
exit( $LOI ? 1 : 0 );
