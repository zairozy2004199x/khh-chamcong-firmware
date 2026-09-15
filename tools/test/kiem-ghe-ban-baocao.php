<?php
/**
 * CANH BA CHỖ KHAI SỐ BẢN CỦA vhcp-ghe BẰNG NHAU.
 *
 *   (1) header `Version:`           — vhcp-ghe/vhcp-ghe.php
 *   (2) hằng VHG_VERSION            — vhcp-ghe/vhcp-ghe.php
 *   (3) VHG_BaoCao::BAN             — vhcp-ghe/includes/class-vhg-baocao.php
 *
 * Vì sao có chỗ thứ ba (15/09/2026): trên host thật, góc màn in đúng bản mới (từ vhcp-ghe.php)
 * mà lớp VHG_BaoCao chạy hành vi bản cũ — tệp lớp bị opcache giữ lại hoặc không được ghi đè.
 * Không gác nào trong mã bắt được; chỉ một VÂN TAY nằm TRONG tệp lớp (const BAN), do boot() trả
 * ra và vhg_soat_tep_lop() so với VHG_VERSION, mới bắt được. Bộ soát ấy chỉ đúng khi ba chỗ
 * bằng nhau: quên tăng BAN là host báo ĐỎ dù mã đúng — lỗi ở bộ canh, đổ tội cho đúng thứ đang canh.
 *
 * Chạy: php tools/test/kiem-ghe-ban-baocao.php   (chay-het.sh tự gom)
 */
$goc   = dirname( __DIR__, 2 );
$chinh = (string) file_get_contents( $goc . '/vhcp-ghe/vhcp-ghe.php' );
$bc    = (string) file_get_contents( $goc . '/vhcp-ghe/includes/class-vhg-baocao.php' );

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
t( "🔴 header == VHG_VERSION ($h vs $v)",        '?' !== $h && $h === $v );
t( "🔴 VHG_VERSION == VHG_BaoCao::BAN ($v vs $b)", '?' !== $v && $v === $b );

/* Đường đi của vân tay phải liền mạch: lớp khai → boot() trả → tệp chính so. Đứt một khúc là
   bộ soát im lặng nói "khớp" trong khi chẳng so gì. Đếm theo CHUỖI GỌI, đúng bài học §6 CLAUDE.md. */
t( 'boot() trả banBc lấy từ self::BAN',                    false !== strpos( $bc, "'banBc' => self::BAN" ) );
t( 'vhcp-ghe.php có bộ tự soát vhg_soat_tep_lop()',        false !== strpos( $chinh, 'function vhg_soat_tep_lop' ) );
t( 'tự soát dùng defined() — không chạm thẳng hằng trên lớp cũ (fatal)',
	false !== strpos( $chinh, "defined( 'VHG_BaoCao::BAN' )" ) );
t( 'tự soát đọc thẳng tệp trên đĩa để phân biệt opcache/đĩa',
	false !== strpos( $chinh, "includes/class-vhg-baocao.php';" ) && false !== strpos( $chinh, 'file_get_contents( $tep )' ) );
t( 'khớp thì XOÁ option vhg_tep_lech (không treo thông báo cũ)',
	false !== strpos( $chinh, "delete_option( 'vhg_tep_lech' )" ) );

echo "\n" . ( $LOI ? "ĐỎ: $LOI/$SO phép hỏng" : "SẠCH: $SO phép" ) . "\n";
exit( $LOI ? 1 : 0 );
