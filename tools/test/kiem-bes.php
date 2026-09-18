<?php
/**
 * ĐỌC BÁO CÁO BES — VÀ KHÔNG BAO GIỜ NHÂN ĐÔI DOANH THU.
 *
 * 18/09/2026 anh Thắng thêm một cửa hàng chạy hệ Bes (không phải FABi) và gửi báo cáo
 * "TỔNG HỢP MÓN ĂN BÁN" của nó.
 *
 * 🔴 BẪY CHÍNH — đo trên file thật trước khi viết một dòng mã nào:
 *      cộng riêng hàng MÓN   : 5.970.000
 *      cộng riêng hàng NHÓM  : 5.970.000
 *      cộng CẢ HAI           : 11.940.000   <- GẤP ĐÔI
 *    Báo cáo xen hàng nhóm (tổng con) lẫn hàng món vào cùng một bảng. Cộng hết là gấp đôi, mà
 *    gấp đôi một cách trông rất hợp lý: không dòng nào âm, không dòng nào lạ, tổng vẫn là một
 *    số tròn trịa. Đúng loại lỗi không ai soi ra bằng mắt.
 *
 * 🔴 VÀ CHỐT NGƯỢC LÀ PHẦN ĐẮT NHẤT: bộ đọc phải CHỐI khi số nó cộng ra không khớp dòng "Tổng"
 *    do Bes tự in. Không có chốt ấy thì một lần Bes đổi thứ tự cột là doanh thu vào sổ sai mà
 *    không có gì báo.
 *
 * 🔴 BÀI NÀY TỰ DỰNG FILE MẪU. Kho công khai — không mang doanh thu thật của cửa hàng vào đây.
 *    File mẫu giữ nguyên HÌNH DẠNG file thật (dòng tên cửa hàng, dòng tiêu đề "TỔNG HỢP MÓN ĂN
 *    BÁN", cột trống xen giữa, hàng nhóm không có tên hàng, dòng Tổng, chân trang có ngày),
 *    còn số liệu thì bịa.
 *
 * Chạy: php tools/test/kiem-bes.php
 */

require_once __DIR__ . '/wp-stub.php';
if ( ! function_exists( 'number_format_i18n' ) ) {
	function number_format_i18n( $n, $d = 0 ) { return number_format( $n, $d ); }
}

$goc = dirname( __DIR__, 2 ) . '/wordpress/khh-doanh-thu';
require_once $goc . '/doc-file.php';
require_once $goc . '/bes.php';

$dat = 0; $hong = array();
function phep( $ten, $dung ) {
	global $dat, $hong;
	if ( $dung ) { $dat++; } else { $hong[] = $ten; }
}

/**
 * Dựng file .csv đúng hình dạng Bes xuất ra.
 *
 * @param array $nhom [ tên nhóm => [ [mã, tên, giá, số lượng, thành tiền], … ] ]
 * @param mixed $tong Ghi đè dòng "Tổng" (null = tự cộng đúng; false = bỏ hẳn dòng Tổng).
 * @param bool  $co_ngay Có in chân trang mang ngày hay không.
 */
function dung_file_bes( $nhom, $tong = null, $co_ngay = true ) {
	$d = array();
	$d[] = array( 'FUNZONE KVC', '', '', '', '', '', '', '', '', '', '', '', '', '', '' );
	$d[] = array_fill( 0, 15, '' );
	$d[] = array( 'TỔNG HỢP MÓN ĂN BÁN***', '', '', '', '', '', '', '', '', '', '', '', '', '', '' );
	$d[] = array_fill( 0, 15, '' );
	$d[] = array_fill( 0, 15, '' );
	/* Cột 3, 4, 9, 15 CỐ Ý để trống — file thật có mấy cột trống xen giữa, và bộ đọc phải dò
	   cột theo TÊN chứ không theo vị trí. */
	$d[] = array( 'Mã hàng', 'Tên hàng', '', '', 'Đvt', 'Giá bán', 'Số lượng',
		'Tổng tiền trước giảm giá', '', 'Tiền giảm giá', 'Tiền chiết khấu',
		'Tiền phí dịch vụ', 'Tiền thuế', 'Thành tiền', '' );

	$t_tien = 0; $t_sl = 0;
	foreach ( $nhom as $ten_nhom => $ds ) {
		$n_tien = 0; $n_sl = 0;
		foreach ( $ds as $m ) { $n_sl += $m[3]; $n_tien += $m[4]; }
		/* HÀNG NHÓM: có mã (cột 1) mà KHÔNG có tên hàng (cột 2) — chính là cái bẫy. */
		$h = array_fill( 0, 15, '' );
		$h[0] = $ten_nhom; $h[6] = $n_sl; $h[7] = $n_tien;
		$h[9] = 0; $h[10] = 0; $h[11] = 0; $h[12] = 0; $h[13] = $n_tien;
		$d[] = $h;
		foreach ( $ds as $m ) {
			$h = array_fill( 0, 15, '' );
			$h[0] = $m[0]; $h[1] = $m[1]; $h[5] = $m[2]; $h[6] = $m[3]; $h[7] = $m[4];
			$h[9] = 0; $h[10] = 0; $h[11] = 0; $h[12] = 0; $h[13] = $m[4];
			$d[] = $h;
		}
		$t_tien += $n_tien; $t_sl += $n_sl;
	}
	if ( false !== $tong ) {
		$h = array_fill( 0, 15, '' );
		$h[0] = '                Tổng';
		$h[6] = $t_sl; $h[7] = $t_tien;
		$h[13] = ( null === $tong ) ? $t_tien : $tong;
		$d[] = $h;
	}
	if ( $co_ngay ) {
		$h = array_fill( 0, 15, '' );
		$h[0] = ', Ngày 17 Tháng 9 Năm 2026 20:58:05';
		$d[] = $h;
	}

	$tep = tempnam( sys_get_temp_dir(), 'bes' ) . '.csv';
	$f   = fopen( $tep, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	/* Truyền đủ 4 tham số: PHP 8.4 cảnh báo nếu thiếu $escape, và bệ đỡ bộ thử coi cảnh báo
	   là HỎNG — đúng vậy, vì cảnh báo hôm nay là lỗi của bản PHP sau. */
	foreach ( $d as $r ) { fputcsv( $f, $r, ',', '"', '\\' ); }
	fclose( $f ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	return $tep;
}

$MAU = array(
	'ĐỒ UỐNG - THÀNH PHẨM' => array(
		array( 'MA_DU1', 'TRÀ CHANH', 35000, 1, 35000 ),
		array( 'MA_DU2', 'TRÀ SỮA', 40000, 1, 40000 ),
		array( 'MA_DU3', 'TRÀ VẢI FREE', 0, 2, 0 ),
	),
	'VÉ COMBO' => array(
		array( 'MA_VE1', 'VÉ COMBO A', 180000, 10, 1800000 ),
		array( 'MA_VE2', 'VÉ COMBO B', 180000, 5, 900000 ),
	),
	'VÉ LẺ' => array(
		array( 'MA_VL1', 'VÉ LẺ T2-T6', 150000, 4, 600000 ),
	),
);
/* Tiền: 35.000 + 40.000 + 0 + 1.800.000 + 900.000 + 600.000 = 3.375.000
   Món : 1 + 1 + 2 + 10 + 5 + 4 = 23 · Vé: 10 + 5 + 4 = 19 */

/* ── 1. Đọc đúng, và KHÔNG nhân đôi ─────────────────────────────────────────────────────── */
$tep = dung_file_bes( $MAU );
phep( 'nhận mặt được file Bes', khh_dt_la_file_bes( $tep ) );
$r = khh_dt_doc_bes( $tep );
phep( 'đọc được, không lỗi', ! is_wp_error( $r ) );
if ( ! is_wp_error( $r ) ) {
	$d = $r['dong'][0];
	/* 🔴 Phép quan trọng nhất cả bài: 3.375.000, KHÔNG phải 6.750.000. */
	phep( '🔴 thành tiền = 3.375.000 (hàng nhóm KHÔNG bị cộng vào)', 3375000.0 === (float) $d['thanh_tien'] );
	phep( 'số món = 23 (cũng không nhân đôi)', 23.0 === (float) $d['so_mon'] );
	phep( 'số vé = 19, nhận theo tên NHÓM', 19.0 === (float) $d['so_ve'] );
	phep( 'đọc được ngày từ chân trang', '2026-09-17' === $d['ngay'] );
	phep( 'lấy tên cơ sở ở dòng đầu file', 'FUNZONE KVC' === $d['cua_hang'] );
	/* ⚠️ pttt PHẢI rỗng. Đoán "coi hết là tiền mặt" thì màn đối soát sẽ tố cửa hàng giữ tiền. */
	phep( '🔴 pttt để RỖNG, không đoán hình thức thanh toán', array() === $d['pttt'] );
	phep( 'không bịa số hoá đơn (báo cáo theo món, không có mã HĐ)', 0 === (int) $d['so_hd'] );
	phep( 'món xếp theo tiền giảm dần', 'VÉ COMBO A' === $d['mon'][0]['n'] );
	phep( 'món mang theo tên nhóm', 'VÉ COMBO' === $d['mon'][0]['g'] );
}

/* ── 2. 🔴 CHỐT TỔNG: lệch là CHỐI ──────────────────────────────────────────────────────── */
$tep_lech = dung_file_bes( $MAU, 9999999 );
$r2 = khh_dt_doc_bes( $tep_lech );
phep( '🔴 dòng Tổng lệch thì CHỐI, không nạp', is_wp_error( $r2 ) );
if ( is_wp_error( $r2 ) ) {
	phep( 'câu chối nói ra CẢ HAI con số', false !== strpos( $r2->get_error_message(), '3.375.000' )
		|| false !== strpos( $r2->get_error_message(), '3,375,000' ) );
}
$tep_khong_tong = dung_file_bes( $MAU, false );
phep( '🔴 thiếu dòng Tổng thì cũng CHỐI (không còn gì chặn lượt đọc lệch cột)',
	is_wp_error( khh_dt_doc_bes( $tep_khong_tong ) ) );

/* ── 3. Thiếu ngày thì chối, vì ghi sai ngày là ghi sai sổ ──────────────────────────────── */
phep( 'thiếu chân trang mang ngày thì chối',
	is_wp_error( khh_dt_doc_bes( dung_file_bes( $MAU, null, false ) ) ) );

/* ── 4. Đặt tên cơ sở khác — "FUNZONE" trơ dễ đụng cơ sở sẵn có ─────────────────────────── */
$r3 = khh_dt_doc_bes( $tep, 'FUNZONE Aeon Bình Dương' );
phep( 'đặt được tên cơ sở khác tên trong file',
	! is_wp_error( $r3 ) && 'FUNZONE Aeon Bình Dương' === $r3['dong'][0]['cua_hang'] );

/* ── 5. Không nhận oan file của hệ khác ─────────────────────────────────────────────────── */
$tep_la = tempnam( sys_get_temp_dir(), 'la' ) . '.csv';
file_put_contents( $tep_la, "Thoi gian,Ma giao dich,So tien\n17-09-2026 10:00,123,50000\n" );
phep( 'KHÔNG nhận sổ MoMo là file Bes', ! khh_dt_la_file_bes( $tep_la ) );
phep( 'và đọc nó thì chối', is_wp_error( khh_dt_doc_bes( $tep_la ) ) );

if ( $hong ) {
	echo "\n✗ HỎNG " . count( $hong ) . " phép (đạt $dat):\n";
	foreach ( $hong as $h ) { echo "   · 🔴 $h\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $dat phép: đọc báo cáo Bes, hàng nhóm không nhân đôi, tổng lệch thì chối.\n";
