<?php
/**
 * CANH SỐ BẢN vhcp-ghe VÀ BẢN SAO LỚP BÁO CÁO MANG SỐ BẢN.
 *
 * Ba chỗ khai số bản phải bằng nhau:
 *   (1) header `Version:`      — vhcp-ghe/vhcp-ghe.php
 *   (2) hằng VHG_VERSION       — vhcp-ghe/vhcp-ghe.php
 *   (3) VHG_BaoCao::BAN        — vhcp-ghe/includes/class-vhg-baocao.php
 *
 * Vì sao có (3) và có bản sao (15/09/2026): trên host thật, sáu lần cài liên tiếp, vhcp-ghe.php và
 * class-vhg-trang.php đổi mới mà class-vhg-baocao.php thì KHÔNG — tệp kẹt quyền trên đĩa, zip vẫn
 * "cài thành công". BAN là vân tay nằm TRONG tệp lớp để bắt ca ấy; bản sao mang số bản
 * (includes/class-vhg-baocao-v<VER>.php) là đường né: tên mới thì luôn ghi được. vhcp-ghe.php nạp
 * bản sao trước, tệp gốc chỉ là đường lui. Bài này canh cho đường né không tự hỏng:
 *   · đúng MỘT bản sao (hai bản là nạp nhầm bản cũ mà không ai biết),
 *   · tên đúng theo VHG_VERSION (lệch tên là file_exists trượt → lùi về tệp gốc kẹt → lỗi cũ quay lại
 *     im lặng, dù zip có bản sao),
 *   · byte-y-nguyên với nguồn (sửa tay bản sao là hai bản lệch nhau, không ai biết bản nào đang chạy).
 *
 * Chạy: php tools/test/kiem-ghe-ban-baocao.php   (chay-het.sh tự gom; build bằng tools/build-ghe.sh)
 */
$goc   = dirname( __DIR__, 2 );
$chinh = (string) file_get_contents( $goc . '/vhcp-ghe/vhcp-ghe.php' );
$bcTep = $goc . '/vhcp-ghe/includes/class-vhg-baocao.php';
$bc    = (string) file_get_contents( $bcTep );

$LOI = 0; $SO = 0;
function t( $ten, $ok ) {
	global $LOI, $SO; $SO++;
	echo ( $ok ? '  ✓ ' : '  ✗ ' ) . $ten . "\n";
	if ( ! $ok ) { $LOI++; }
}

preg_match( '/^ \* Version:\s+([0-9][0-9.]*)/m', $chinh, $mh );
preg_match( "/define\(\s*'VHG_VERSION',\s*'([0-9][0-9.]*)'\s*\)/", $chinh, $mv );
preg_match( "/const BAN = '([0-9][0-9.]*)';/", $bc, $mb );
$h = isset( $mh[1] ) ? $mh[1] : '?';
$v = isset( $mv[1] ) ? $mv[1] : '?';
$b = isset( $mb[1] ) ? $mb[1] : '?';

echo "── Số bản vhcp-ghe: header $h · VHG_VERSION $v · VHG_BaoCao::BAN $b ──\n";
t( 'đọc được header Version',      '?' !== $h );
t( 'đọc được VHG_VERSION',         '?' !== $v );
t( 'đọc được VHG_BaoCao::BAN',     '?' !== $b );
t( "🔴 header == VHG_VERSION ($h vs $v)",          '?' !== $h && $h === $v );
t( "🔴 VHG_VERSION == VHG_BaoCao::BAN ($v vs $b)", '?' !== $v && $v === $b );

/* Chuỗi gọi vân tay phải liền mạch: lớp khai → boot() trả → tệp chính so. Đứt một khúc là bộ soát
   im lặng nói "khớp" trong khi chẳng so gì. Đếm theo CHUỖI GỌI, đúng bài học §6 CLAUDE.md. */
t( 'boot() trả banBc lấy từ self::BAN',                       false !== strpos( $bc, "'banBc' => self::BAN" ) );
t( 'vhcp-ghe.php có bộ tự soát vhg_soat_tep_lop()',           false !== strpos( $chinh, 'function vhg_soat_tep_lop' ) );
t( 'tự soát dùng defined() — không chạm thẳng hằng trên lớp cũ (fatal)',
	false !== strpos( $chinh, "defined( 'VHG_BaoCao::BAN' )" ) );
t( 'tự soát đọc thẳng tệp trên đĩa (vhg_ban_tren_dia_) để phân biệt opcache/đĩa',
	false !== strpos( $chinh, 'function vhg_ban_tren_dia_' ) && false !== strpos( $chinh, 'file_get_contents( $tep )' ) );
t( 'khớp thì XOÁ option vhg_tep_lech (không treo thông báo cũ)',
	false !== strpos( $chinh, "delete_option( 'vhg_tep_lech' )" ) );

/* ── BẢN SAO MANG SỐ BẢN ─────────────────────────────────────────────────────────────────── */
echo "── Bản sao lớp báo cáo mang số bản ──\n";
$saoDs = glob( $goc . '/vhcp-ghe/includes/class-vhg-baocao-v*.php' );
$saoDs = is_array( $saoDs ) ? $saoDs : array();
t( '🔴 có ĐÚNG MỘT bản sao includes/class-vhg-baocao-v*.php (đang có ' . count( $saoDs ) . ')', 1 === count( $saoDs ) );
$saoTen = count( $saoDs ) ? basename( $saoDs[0] ) : '(không có)';
t( "🔴 tên bản sao đúng theo VHG_VERSION (class-vhg-baocao-v$v.php, đang có $saoTen)",
	1 === count( $saoDs ) && $saoTen === "class-vhg-baocao-v$v.php" );
t( '🔴 bản sao byte-y-nguyên với nguồn (md5 bằng nhau)',
	1 === count( $saoDs ) && md5_file( $saoDs[0] ) === md5_file( $bcTep ) );
t( 'vhcp-ghe.php dựng đường bản sao từ VHG_VERSION',
	false !== strpos( $chinh, "includes/class-vhg-baocao-v' . VHG_VERSION . '.php'" ) );
t( 'vhcp-ghe.php nạp bản sao TRƯỚC, tệp gốc chỉ là đường lui — đúng MỘT require_once',
	false !== strpos( $chinh, 'require_once file_exists( $vhg_bc_sao ) ? $vhg_bc_sao : VHG_DIR . \'includes/class-vhg-baocao.php\';' )
	&& 1 === preg_match_all( '/require_once[^;]*class-vhg-baocao/', $chinh ) );
t( 'tools/build-ghe.sh có mặt và xoá bản sao cũ trước khi chép bản mới',
	is_file( $goc . '/tools/build-ghe.sh' )
	&& false !== strpos( (string) file_get_contents( $goc . '/tools/build-ghe.sh' ), 'rm -f vhcp-ghe/includes/class-vhg-baocao-v*.php' ) );

/* ── MỘT NGUỒN PHẠM VI ─────────────────────────────────────────────────────────────────────
   Đếm theo CHỖ GỌI, không đếm chuỗi (§6 CLAUDE.md, bài học 0.18.1): luật dựng phạm vi từng có BA
   bản sao viết bằng ba đoạn mã khác nhau — boot(), phien_tinh() và doi_chieu(). Vá một chỗ thì hai
   chỗ kia vẫn sai, sinh ra cảnh ô chọn cơ sở ra 0 trong khi thanh Tiến độ ra 67 cơ sở kèm doanh thu
   cả chuỗi (15/09/2026). Nay cả ba phải đi qua pham_vi_man_, và ds_ghe() chỉ được gọi TRONG hàm ấy. */
echo "── Một nguồn phạm vi (pham_vi_man_) ──\n";
$goiPv = preg_match_all( '/self::pham_vi_man_\(/', $bc );
$goiDs = preg_match_all( '/self::ds_ghe\(/', $bc );
t( "🔴 pham_vi_man_ được gọi đúng 3 chỗ (boot · phien_tinh · doi_chieu) — đang có $goiPv", 3 === $goiPv );
t( "🔴 ds_ghe chỉ còn gọi TRONG pham_vi_man_ (2 lượt: dựng + dựng lại sau cứu-theo-tên) — đang có $goiDs", 2 === $goiDs );
t( 'pham_vi_man_ trả đủ q/ghe/cs/toan_quyen',
	1 === preg_match( "/return array\( 'q' => \\\$q, 'ghe' => \\\$ghe, 'cs' => \\\$cs,/", $bc )
	&& false !== strpos( $bc, "'toan_quyen' =>" ) );

echo "\n" . ( $LOI ? "ĐỎ: $LOI/$SO phép hỏng" : "SẠCH: $SO phép" ) . "\n";
exit( $LOI ? 1 : 0 );
