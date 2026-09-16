<?php
/**
 * CANH SỐ BẢN vhcp-cham-cong VÀ BỘ TỰ CẬP NHẬT CỦA NÓ.
 *
 * Hai chỗ khai số bản phải bằng nhau:
 *   (1) header `Version:`   — vhcp-cham-cong/vhcp-cham-cong.php
 *   (2) hằng VHCC_VERSION   — cùng tệp
 *
 * ⚠️ Với plugin này, hằng (2) KHÔNG chỉ để khoe: `vhcc_maybe_upgrade()` so nó với option
 *    `vhcc_ver` để quyết định có chạy `VHCC_DB::install()` hay không. Quên tăng hằng là bản mới
 *    lên host mà lược đồ bảng KHÔNG được nâng — hỏng im lặng, chỉ lộ ra khi một màn nào đó truy
 *    vấn cột chưa có.
 *
 * 🔴 VÌ SAO BÀI NÀY ĐẾM CHỖ GỌI (§6 CLAUDE.md, bài học 0.18.1). Bộ tự cập nhật là lớp thứ BA chép
 *    cùng một luật (Ghế → Sao Kê → Chấm Công). Định nghĩa một lớp mà quên gọi `init()` thì nó là
 *    MÃ CHẾT: không móc nào được gắn, WordPress không thấy bản mới, trang /it không thấy dòng nào
 *    — và không có gì đỏ cả. Đúng loại lỗi `cong_nhan_webhook()` đã dính ở sao kê 0.20.0.
 *
 * Chạy: php tools/test/kiem-chamcong-ban.php   (chay-het.sh tự gom)
 */
$goc   = dirname( __DIR__, 2 );
$chinh = (string) file_get_contents( $goc . '/vhcp-cham-cong/vhcp-cham-cong.php' );
$cnTep = $goc . '/vhcp-cham-cong/tu-cap-nhat.php';
$cn    = is_file( $cnTep ) ? (string) file_get_contents( $cnTep ) : '';

$LOI = 0; $SO = 0;
function t( $ten, $ok ) {
	global $LOI, $SO; $SO++;
	echo ( $ok ? '  ✓ ' : '  ✗ ' ) . $ten . "\n";
	if ( ! $ok ) { $LOI++; }
}

preg_match( '/^ \* Version:\s+([0-9][0-9.]*)/m', $chinh, $mh );
preg_match( "/define\(\s*'VHCC_VERSION',\s*'([0-9][0-9.]*)'\s*\)/", $chinh, $mv );
$h = isset( $mh[1] ) ? $mh[1] : '?';
$v = isset( $mv[1] ) ? $mv[1] : '?';

echo "── Số bản vhcp-cham-cong: header $h · VHCC_VERSION $v ──\n";
t( 'đọc được header Version',   '?' !== $h );
t( 'đọc được VHCC_VERSION',     '?' !== $v );
t( "🔴 header == VHCC_VERSION ($h vs $v)", '?' !== $h && $h === $v );
t( 'hằng VHCC_VERSION vẫn là thứ vhcc_maybe_upgrade() so với option vhcc_ver',
	1 === preg_match( "/get_option\(\s*'vhcc_ver'\s*\)\s*!==\s*VHCC_VERSION/", $chinh ) );

echo "── Bộ tự cập nhật ──\n";
t( 'có tệp vhcp-cham-cong/tu-cap-nhat.php', '' !== $cn );
t( '🔴 tệp ấy được NẠP trong vhcp-cham-cong.php',
	false !== strpos( $chinh, "require_once VHCC_DIR . 'tu-cap-nhat.php';" ) );
/* Mã chết không bao giờ đỏ — chỉ phép đếm CHỖ GỌI mới thấy. */
$goiInit = preg_match_all( '/VHCC_TuCapNhat::init\(\)/', $chinh );
t( "🔴 VHCC_TuCapNhat::init() được gọi đúng 1 chỗ — đang có $goiInit", 1 === $goiInit );
t( 'nạp SAU khi khai VHCC_VERSION (lớp đọc hằng ấy để tự xưng)',
	false !== strpos( $chinh, "define( 'VHCC_VERSION'" )
	&& strpos( $chinh, "define( 'VHCC_VERSION'" ) < strpos( $chinh, "require_once VHCC_DIR . 'tu-cap-nhat.php';" ) );

t( 'nhánh phát triển khớp CLAUDE.md §3',
	false !== strpos( $cn, "const NHANH = 'claude/posh-qr-kh1urz';" ) );
t( 'repo công khai đúng tên',
	false !== strpos( $cn, "const REPO = 'zairozy2004199x/khh-chamcong-firmware';" ) );
t( '🔴 CHỈ NÂNG, KHÔNG LÙI — version_compare(..., <=) thì coi như không có bản mới',
	false !== strpos( $cn, "version_compare( \$ver, \$hien, '<=' )" ) );
/* Lấy đúng dòng preg_match dò số bản trong tu-cap-nhat.php rồi soi nó — đừng so chuỗi regex
   có escape, vì số dấu `\` khác nhau giữa tệp bị soi và bài soi là bài đỏ ở chỗ mã không sai. */
preg_match( '/preg_match\(\s*"([^"]*)"\s*,\s*\$body/', $cn, $mdo );
$doSo = isset( $mdo[1] ) ? $mdo[1] : '';
t( '🔴 đọc số bản bằng hằng VHCC_VERSION chứ không bằng header Version:',
	'' !== $doSo && false !== strpos( $doSo, 'VHCC_VERSION' ) && false === strpos( $doSo, 'Version:' ) );
t( 'ô nhớ RIÊNG, không giẫm lên ô của Ghế/Sao Kê',
	false !== strpos( $cn, "const O_NHO   = 'vhcc_gh_ban_moi';" ) );
t( 'tự khai vào danh sách chung (trang /it dựng bảng từ bộ lọc này)',
	false !== strpos( $cn, "add_filter( 'vhcp_tu_cap_nhat_ds', array( __CLASS__, 'khai_ds' ) );" ) );
t( 'khai_ds trả đủ ma/ten/duong/hien/lop — thiếu khoá nào là trang /it vẽ ra dòng trống',
	1 === preg_match( "/'ma'\s*=> self::MA,/", $cn )
	&& false !== strpos( $cn, "'duong' => self::duong()," )
	&& false !== strpos( $cn, "'hien'  => (string) self::ban_hien()," )
	&& false !== strpos( $cn, "'lop'   => __CLASS__," ) );
t( 'ban_moi_nho() KHÔNG gọi mạng (chỉ đọc transient)',
	1 === preg_match( '/public static function ban_moi_nho\(\).*?\}/s', $cn, $mn )
	&& false === strpos( $mn[0], 'wp_remote_get' ) );
t( 'có quen_nho() cho nút "Kiểm tra ngay"',
	false !== strpos( $cn, 'public static function quen_nho()' ) );
t( 'gác tên thư mục sau giải nén về đúng vhcp-cham-cong',
	false !== strpos( $cn, "add_filter( 'upgrader_source_selection'" )
	&& false !== strpos( $cn, "trailingslashit( dirname( \$nguon ) ) . self::MA" ) );

echo "── Gói cài đặt ──\n";
$zip = $goc . '/dist/vhcp-cham-cong.zip';
t( 'dist/vhcp-cham-cong.zip có mặt (bộ cập nhật tải thẳng tệp này qua raw)', is_file( $zip ) );
t( 'tools/build-chamcong.sh có mặt và tự diff zip với cây nguồn',
	is_file( $goc . '/tools/build-chamcong.sh' )
	&& false !== strpos( (string) file_get_contents( $goc . '/tools/build-chamcong.sh' ), 'diff -rq' ) );
/* ⚠️ Model khuôn mặt (~7MB) phải NẰM NGOÀI gói: cài đè zip là WordPress xoá sạch thư mục plugin
   cũ, model nằm trong plugin là mỗi lượt cập nhật bay một lần. Tự cập nhật làm việc cài đè ấy
   xảy ra thường xuyên hơn nhiều, nên bẫy này đáng canh. */
if ( is_file( $zip ) ) {
	$ds = array();
	$zz = new ZipArchive();
	if ( true === $zz->open( $zip ) ) {
		for ( $i = 0; $i < $zz->numFiles; $i++ ) { $ds[] = $zz->getNameIndex( $i ); }
		$zz->close();
	}
	$nang = array_filter( $ds, function ( $x ) {
		return (bool) preg_match( '/(face-api|_model-shard|_model-weights_manifest)/', $x );
	} );
	t( '🔴 zip KHÔNG chứa model khuôn mặt (chỗ đúng là wp-content/uploads/vhcc-mat/)', ! $nang );
	t( 'zip chứa cả tu-cap-nhat.php', in_array( 'vhcp-cham-cong/tu-cap-nhat.php', $ds, true ) );
	$mainZ = '';
	$zz = new ZipArchive();
	if ( true === $zz->open( $zip ) ) {
		$mainZ = (string) $zz->getFromName( 'vhcp-cham-cong/vhcp-cham-cong.php' );
		$zz->close();
	}
	preg_match( "/define\(\s*'VHCC_VERSION',\s*'([0-9][0-9.]*)'\s*\)/", $mainZ, $mz );
	$vz = isset( $mz[1] ) ? $mz[1] : '?';
	t( "🔴 số bản trong zip == số bản trên cây nguồn ($vz vs $v) — zip cũ là cập nhật vòng tròn", $vz === $v );
}

echo "\n" . ( $LOI ? "ĐỎ: $LOI/$SO phép hỏng" : "SẠCH: $SO phép" ) . "\n";
exit( $LOI ? 1 : 0 );
