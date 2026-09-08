<?php
/**
 * BẢN CHI PHÍ RIÊNG CHO MỘT VÙNG — PHẢI ĐỘC LẬP HOÀN TOÀN VỚI BẢN ĐANG CHẠY.
 *
 * Anh Thắng 08/09/2026: *"khu vực hn muốn dùng hệ thống vận hành chi phí … build 1 web riêng để
 * tránh sai dữ liệu"*, rồi chốt *"tạo riêng, tức hn họ có quy trình khác thì đổi lại để không
 * ảnh hưởng hcm"*.
 *
 * =============================================================================================
 * 🔴 HAI BẢN CÙNG CÀI TRÊN MỘT WORDPRESS. Sót một chỗ đổi tên là hỏng theo bốn kiểu:
 *      · trùng tên lớp  -> PHP chết ngay lúc nạp, TRẮNG CẢ SITE (kể cả bản đang chạy)
 *      · trùng tên bảng -> hai vùng ghi đè dữ liệu của nhau, IM LẶNG, không câu lỗi nào
 *      · trùng khoá cấu hình -> đổi cài đặt vùng này là đổi luôn vùng kia
 *      · trùng đường dẫn -> chỉ một bản mở được
 *    Ba cái đầu đều là mất tiền hoặc mất cả trang, nên bài này soi TỪNG cái tên một.
 *
 * ⚠️ CHỖ MÙ, ghi rõ: bài này KHÔNG chạy WordPress thật. Nó soi mã nguồn — đủ để bắt mọi kiểu
 *    "sót một chỗ đổi tên", nhưng không kiểm được `dbDelta` dựng bảng ra sao trên host.
 *
 * Chạy: php tools/test/kiem-tach-ban-vung.php
 */

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : var_export( $them, true ) ) ) : '' );
}
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . var_export( $mong, true ) . ')', $mong === $thuc, $thuc ); }

$GOC  = dirname( dirname( __DIR__ ) );
$GOCP = $GOC . '/wordpress/vhcp-chi-phi';
$MA   = 'hn';
$DICH = $GOC . '/wordpress/vhcp-chi-phi-' . $MA;

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 0. BẢN VÙNG PHẢI CÓ THẬT, VÀ SINH RA ĐƯỢC LẠI
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
t( '🔴 có bản vùng ' . $MA, is_dir( $DICH ), $DICH );
t( 'có script sinh (không chép tay)', is_file( $GOC . '/tools/tach-ban-vung.sh' ) );
t( 'tệp gốc plugin mang đúng tên', is_file( $DICH . '/vhcp-chi-phi-' . $MA . '.php' ) );
if ( ! is_dir( $DICH ) ) { echo "\n✗ Chưa sinh bản vùng — dừng.\n"; exit( 1 ); }

/** Mọi tệp mã của một thư mục plugin. */
function tep_ma( $thu_muc ) {
	$ra = array();
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $thu_muc, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $f ) {
		if ( preg_match( '/\.(php|html|js)$/i', $f->getFilename() ) ) { $ra[] = $f->getPathname(); }
	}
	sort( $ra );
	return $ra;
}
$tep_dich = tep_ma( $DICH );
t( 'bản vùng có đủ tệp mã', count( $tep_dich ) >= 20, count( $tep_dich ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 1. 🔴 KHÔNG CÒN MỘT TÊN NÀO CỦA BẢN GỐC
 *
 * Đây là phép quan trọng nhất của cả bài: một chuỗi `VHCP_` còn sót là một lớp trùng tên, và
 * PHP chết ngay lúc nạp — trắng cả site, kể cả bản HCM đang chạy.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$sot_hoa = array(); $sot_thuong = array();
foreach ( $tep_dich as $f ) {
	$s = file_get_contents( $f );
	$ten_ngan = str_replace( $DICH . '/', '', $f );
	/* `VHCP_` mà KHÔNG có mã vùng ngay sau = còn sót. */
	if ( preg_match_all( '/VHCP_(?!' . strtoupper( $MA ) . ')/', $s, $m ) ) {
		$sot_hoa[] = $ten_ngan . ' (' . count( $m[0] ) . ' chỗ)';
	}
	if ( preg_match_all( '/\bvhcp_(?!' . $MA . ')/', $s, $m2 ) ) {
		$sot_thuong[] = $ten_ngan . ' (' . count( $m2[0] ) . ' chỗ)';
	}
}
teq( '🔴 KHÔNG còn tên lớp / hằng nào của bản gốc (VHCP_)', array(), $sot_hoa );
teq( '🔴 KHÔNG còn tiền tố bảng / khoá cấu hình nào của bản gốc (vhcp_)', array(), $sot_thuong );

/* Và phải đổi THẬT — không phải thư mục rỗng. */
$so_lop = 0;
foreach ( $tep_dich as $f ) {
	$so_lop += preg_match_all( '/class\s+VHCP' . strtoupper( $MA ) . '_/', file_get_contents( $f ) );
}
t( '🔴 có tên lớp mang mã vùng', $so_lop >= 15, $so_lop );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 2. TÊN BẢNG CSDL — HAI BÊN KHÔNG ĐƯỢC CHẠM NHAU
 *
 * 🔴 Trùng bảng là kiểu hỏng TỆ NHẤT: không câu lỗi nào, hai vùng lặng lẽ ghi đè lên nhau, và
 *    tới lúc phát hiện thì số liệu đã trộn vào nhau không gỡ ra được.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
function tien_to_bang( $tep ) {
	$s = file_get_contents( $tep );
	return preg_match( "/\\\$wpdb->prefix \. '([a-z0-9_]+)'/", $s, $m ) ? $m[1] : '';
}
$tt_goc  = tien_to_bang( $GOCP . '/includes/class-vhcp-db.php' );
$tt_dich = tien_to_bang( $DICH . '/includes/class-vhcp-db.php' );
teq( 'bản gốc dùng tiền tố bảng', 'vhcp_', $tt_goc );
teq( '🔴 bản vùng dùng tiền tố KHÁC', 'vhcp' . $MA . '_', $tt_dich );
t( '   và hai tiền tố không lồng nhau', 0 !== strpos( $tt_dich, $tt_goc ), array( $tt_goc, $tt_dich ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 3. ĐƯỜNG DẪN TRANG RIÊNG
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$app_goc  = file_get_contents( $GOCP . '/includes/class-vhcp-app.php' );
$app_dich = file_get_contents( $DICH . '/includes/class-vhcp-app.php' );
t( 'bản gốc vẫn ở /chi-phi', false !== strpos( $app_goc, "'chi-phi'" ), '' );
t( '🔴 bản vùng ở /chi-phi-' . $MA, false !== strpos( $app_dich, "'chi-phi-" . $MA . "'" ), '' );
/* ⚠️ `preg_match()` trả 0 khi KHÔNG khớp, không trả false. So bằng `false ===` là phép này đỏ
   ngay cả lúc mã hoàn toàn đúng — đã đỏ đúng như thế ở lượt chạy đầu. */
teq( '   và KHÔNG còn đường dẫn cũ', 0, preg_match( "/'chi-phi'/", $app_dich ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 4. TÊN PLUGIN KHÁC — không thì màn Plugin bày hai dòng giống hệt
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$boot_goc  = file_get_contents( $GOCP . '/vhcp-chi-phi.php' );
$boot_dich = file_get_contents( $DICH . '/vhcp-chi-phi-' . $MA . '.php' );
preg_match( '/Plugin Name:\s*(.+)/', $boot_goc, $n1 );
preg_match( '/Plugin Name:\s*(.+)/', $boot_dich, $n2 );
t( 'bốc được tên hai plugin', ! empty( $n1[1] ) && ! empty( $n2[1] ), array( $n1, $n2 ) );
t( '🔴 tên hai plugin KHÁC nhau', trim( $n1[1] ) !== trim( $n2[1] ), array( trim( $n1[1] ), trim( $n2[1] ) ) );
t( '   và tên bản vùng nhắc tới vùng', false !== mb_stripos( $n2[1], 'HN' ) || false !== mb_stripos( $n2[1], 'Hà Nội' ), $n2[1] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 5. MÃ VẪN ĐỌC ĐƯỢC SAU KHI ĐỔI TÊN
 *
 * 🔴 Đổi tên bằng `sed` mà lỡ tay là sinh ra mã sai cú pháp — và nó chỉ lộ ra lúc kích hoạt
 *    plugin trên host thật, tức lúc site đã trắng.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$hong = array();
foreach ( $tep_dich as $f ) {
	if ( ! preg_match( '/\.php$/i', $f ) ) { continue; }
	$out = array(); $ma = 0;
	exec( escapeshellcmd( PHP_BINARY ) . ' -l ' . escapeshellarg( $f ) . ' 2>&1', $out, $ma );
	if ( 0 !== $ma ) { $hong[] = str_replace( $DICH . '/', '', $f ) . ': ' . implode( ' ', $out ); }
}
teq( '🔴 mọi tệp PHP của bản vùng đọc được', array(), $hong );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 6. BẢN GỐC KHÔNG BỊ ĐỤNG MỘT DÒNG NÀO
 *
 * 🔴 Anh Thắng chốt: *"để không ảnh hưởng hcm"*. Đây là phép canh đúng câu ấy.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$goc_sot = array();
foreach ( tep_ma( $GOCP ) as $f ) {
	if ( false !== strpos( $f, '/goc/' ) ) { continue; }   // mã gốc tra cứu, không chạy
	$s = file_get_contents( $f );
	if ( preg_match( '/VHCP' . strtoupper( $MA ) . '_|vhcp' . $MA . '_|chi-phi-' . $MA . '/', $s ) ) {
		$goc_sot[] = str_replace( $GOCP . '/', '', $f );
	}
}
teq( '🔴 bản gốc KHÔNG dính một chữ nào của bản vùng', array(), $goc_sot );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 7. SCRIPT SINH PHẢI GÁC MẤY CHỖ DỄ CHẾT
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$sh = file_get_contents( $GOC . '/tools/tach-ban-vung.sh' );
t( '🔴 chối mã vùng có dấu / hoa / ký tự lạ (nó đi vào TÊN LỚP và TÊN BẢNG)',
	false !== strpos( $sh, "grep -qE '^[a-z][a-z0-9]{0,7}\$'" ), '' );
/* Đòi cả LỜI HỎI lẫn CHỐT KIỂM. Dò trống chữ `DONG-Y` thì bỏ hẳn dòng kiểm đi vẫn khớp vào
   lời nhắc ngay trên — phá thử chỉ ra đúng lỗ ấy. */
t( '🔴 hỏi lại trước khi ĐÈ bản vùng đã có', false !== strpos( $sh, 'read -r tra' ), '' );
t( '   và THẬT SỰ dừng nếu không gõ đúng',
	false !== strpos( $sh, '[ "$tra" = "DONG-Y" ] ||' ), '' );
t( '   và nói rõ đè là mất thứ vùng ấy đã sửa riêng', false !== mb_strpos( $sh, 'đã sửa riêng' ), '' );
/* Thứ tự đổi tên: HOA trước, thường sau. Ngược lại thì lượt sau đụng vào kết quả lượt trước. */
$i_hoa = strpos( $sh, 's/VHCP_/VHCP' );
$i_thuong = strpos( $sh, 's/vhcp_/vhcp' );
t( '🔴 đổi chữ HOA trước chữ thường (không thì sinh ra VHCPHN_HN_)',
	false !== $i_hoa && false !== $i_thuong && $i_hoa < $i_thuong, array( $i_hoa, $i_thuong ) );
t( 'bỏ thư mục goc/ khỏi bản vùng', false !== strpos( $sh, 'rm -rf "$DICH/goc"' ), '' );

/* ═══════════════════════════════════════════════════════════════════════════════════════════ */
if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo "  · $x\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: bản vùng độc lập hoàn toàn, bản đang chạy không bị đụng.\n";
