<?php
/**
 * LỚP ĐỔI GIÁ TRỊ CỦA JP: BẢN PHP PHẢI RA Y HỆT BẢN JAVASCRIPT GỐC
 * =============================================================================================
 *
 * 🔴 BÀI NÀY KHÔNG SO PHP VỚI MỘT BẢNG ĐÁP ÁN CHÉP TAY. Nó chạy CHÍNH
 *    `goc/jp-capsule-v2/JP2_01_Core.gs` bằng node (qua `jp-doc-goc.js`) rồi đòi PHP ra đúng
 *    từng giá trị. Lý do: bảng đáp án chép tay là bản sự thật THỨ HAI — em chép sai một ô thì
 *    bài kiểm xanh cho một bản PHP sai, mà chẳng ai biết.
 *
 * ⚠️ Cả hệ JP tính tiền qua mấy hàm này. Lệch một ly ở `num()` là lệch mọi báo cáo, và không
 *    ai đối chiếu nổi bản WordPress với bản Apps Script nữa — mất luôn cách duy nhất để biết
 *    bản chuyển có đúng hay không.
 *
 * ⚠️ Không có node thì bài KHÔNG lặng lẽ bỏ qua — nó báo trượt. Một bài kiểm tự tắt khi thiếu
 *    công cụ là bài kiểm xanh giả: người đọc thấy xanh và tin là đã đối chiếu.
 *
 * Chạy: php tools/test/kiem-jp-doc.php
 */

require_once __DIR__ . '/wp-stub.php';
$goc = dirname( dirname( __DIR__ ) );
if ( ! is_dir( $goc . '/wordpress' ) ) { $goc = dirname( __DIR__, 2 ); }

$dat = 0; $truot = array();
function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( $them === null ? '' : ( "\n      → " . ( is_scalar( $them ) ? var_export( $them, true ) : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) ) );
}
function teq( $ten, $mong, $thuc ) {
	t( $ten . ' (mong ' . json_encode( $mong, JSON_UNESCAPED_UNICODE ) . ')', $mong === $thuc, $thuc );
}

require_once $goc . '/wordpress/vhcp-jp/includes/class-vhjp-doc.php';

// ============================================================ 0. Công cụ phải có
exec( 'node --version 2>/dev/null', $ra_node, $ma_node );
t( '🔴 có node để chạy mã gốc (thiếu là KHÔNG đối chiếu được, không phải "bỏ qua")',
	0 === $ma_node, 'node không chạy được' );
$cau_noi = __DIR__ . '/jp-doc-goc.js';
t( 'có cầu nối jp-doc-goc.js', is_file( $cau_noi ) );
t( '🔴 mã gốc JP còn trong kho', is_file( $goc . '/goc/jp-capsule-v2/JP2_01_Core.gs' ) );

/** Chạy một hàm của MÃ GỐC trên danh sách ca, trả mảng kết quả. */
function goc_chay( $ten, $ca ) {
	global $cau_noi;
	$ra = array(); $ma = 0;
	exec( 'node ' . escapeshellarg( $cau_noi ) . ' ' . escapeshellarg( $ten ) . ' '
		. escapeshellarg( wp_json_encode( $ca ) ) . ' 2>&1', $ra, $ma );
	if ( 0 !== $ma ) { return array( 'loi' => implode( "\n", $ra ) ); }
	return json_decode( implode( '', $ra ), true );
}

/**
 * Đối chiếu MỘT hàm: chạy bản gốc, chạy bản PHP, đòi khớp từng ca.
 *
 * ⚠️ So bằng chuỗi JSON chứ không bằng `===`: bên JS số nguyên và số thực lẫn nhau
 *    (`20000` với `20000.0`), còn `json_encode` in cả hai thành `20000`. Dùng `===` là đỏ
 *    oan hàng loạt vì một khác biệt không hề tồn tại ngoài đời.
 */
function doi_chieu( $ten, $ca ) {
	$g = goc_chay( $ten, $ca );
	if ( isset( $g['loi'] ) ) { t( "🔴 chạy được mã gốc cho $ten()", false, $g['loi'] ); return; }
	t( "mã gốc trả đủ " . count( $ca ) . " ca cho $ten()", count( $g ) === count( $ca ), $g );

	foreach ( $ca as $i => $doi_so ) {
		$php = call_user_func_array( array( 'VHJP_Doc', $ten ), $doi_so );
		$mong = isset( $g[ $i ] ) ? $g[ $i ] : null;
		t( "$ten(" . trim( wp_json_encode( $doi_so ), '[]' ) . ')',
			wp_json_encode( $php ) === wp_json_encode( $mong ),
			'gốc ra ' . wp_json_encode( $mong ) . ', PHP ra ' . wp_json_encode( $php ) );
	}
}

// ============================================================ 1. num() — chỗ có mìn
/* Danh sách này cố tình gồm cả mấy ca SAI của bản gốc. Ghim chúng lại là để ai muốn chữa thì
   phải chữa CÓ CHỦ Ý và sửa cả bài kiểm, chứ không lỡ tay đổi mà không biết. */
doi_chieu( 'num', array(
	array( '20000' ), array( '20,000' ), array( '20000đ' ), array( ' 7 ' ),
	array( '20.000' ),      /* 🔴 ra 20 — mất ba số không */
	array( '1.234.567' ),   /* 🔴 ra 0  — mất sạch */
	array( '1.2.3' ), array( '12.5' ), array( '-500' ), array( '.5' ), array( '5.' ),
	array( '-' ), array( 'abc' ), array( '' ), array( null ), array( 0 ), array( '0' ),
	array( 1234.5 ), array( '  ' ), array( 'x9y9' ),
) );
/* Và nói thẳng con số, không chỉ "khớp với bản gốc" — để người đọc bài kiểm thấy ngay mìn nằm
   ở đâu mà không phải đi chạy node. */
teq( '🔴 "20.000" ra 20 chứ KHÔNG phải 20000 — mìn của bản gốc', 20, VHJP_Doc::num( '20.000' ) );
teq( '🔴 "1.234.567" ra 0 — mìn của bản gốc', 0, VHJP_Doc::num( '1.234.567' ) );

// ============================================================ 2. Chưa nhập khác nhập số 0
doi_chieu( 'blank', array(
	array( '' ), array( null ), array( 0 ), array( '0' ), array( ' ' ), array( 'x' ),
) );
teq( '🔴 số 0 KHÔNG phải "chưa nhập"',  false, VHJP_Doc::blank( 0 ) );
teq( '🔴 chuỗi "0" cũng KHÔNG phải',    false, VHJP_Doc::blank( '0' ) );
teq( 'chuỗi rỗng thì đúng là chưa nhập', true, VHJP_Doc::blank( '' ) );

doi_chieu( 'num_hoac_trong', array(
	array( '' ), array( null ), array( 0 ), array( '0' ), array( '20000' ), array( 'abc' ),
) );
teq( '🔴 ô trống GIỮ NGUYÊN là rỗng, không hoá thành 0', '', VHJP_Doc::num_hoac_trong( '' ) );
teq( 'còn số 0 gõ vào thì ra số 0',                        0,  VHJP_Doc::num_hoac_trong( '0' ) );

// ============================================================ 3. so_anh() — 0 gõ vào là cố ý
doi_chieu( 'so_anh', array(
	array( '' ), array( '', 3 ), array( 0 ), array( '0' ), array( 2 ), array( null, 5 ),
) );
teq( '🔴 số 0 gõ vào giữ là 0 (chỗ đó không cần chụp)', 0, VHJP_Doc::so_anh( 0 ) );
teq( 'ô trống lấy mặc định 1',                          1, VHJP_Doc::so_anh( '' ) );
teq( 'ô trống lấy đúng mặc định được truyền',            3, VHJP_Doc::so_anh( '', 3 ) );

// ============================================================ 4. Ngày tháng
doi_chieu( 'ngay', array(
	array( '2026-09-21' ), array( '21/09/2026' ), array( '1/9/2026' ), array( '2026/9/1' ),
	array( '21-09-2026' ), array( '' ), array( null ), array( 0 ), array( 'hôm qua' ),
	array( '2026-09-21 08:30' ),
) );
doi_chieu( 'dmy', array(
	array( '2026-09-21' ), array( '21/09/2026' ), array( '' ), array( 'hôm qua' ),
) );
doi_chieu( 'ngay_gio', array(
	array( '2026-09-21 08:30' ), array( '' ), array( null ), array( '  x  ' ),
) );
teq( 'không nhận ra hình dạng thì TRẢ NGUYÊN, không nuốt mất', 'hôm qua', VHJP_Doc::ngay( 'hôm qua' ) );

// ============================================================ 5. Bỏ dấu và tiền
doi_chieu( 'norm', array(
	array( 'Phú Quốc' ), array( 'ĐÀ NẴNG' ), array( 'Bình Dương 2' ), array( '' ),
	array( null ), array( '  Hồ Chí Minh  ' ), array( 'A-B_C' ),
) );
doi_chieu( 'money', array(
	array( 20000 ), array( '20000' ), array( 1234567 ), array( 0 ), array( '' ), array( -500 ),
) );
teq( 'tiền in theo lối Việt Nam', '1.234.567', VHJP_Doc::money( 1234567 ) );

doi_chieu( 'str', array(
	array( '  x  ' ), array( null ), array( '' ), array( 0 ), array( 12 ),
) );

// ============================================================ 6. Ngày thật (đối tượng)
/* Bên kia là đối tượng Date, bên này là DateTime — không đẩy qua node được nên kiểm riêng.
   Bản gốc ghim múi giờ 'Asia/Ho_Chi_Minh'; đọc theo múi giờ máy chủ là mọi mốc lệch đúng bằng
   chênh lệch ấy, và đối soát với sao kê ngân hàng thành mò kim. */
$d = new DateTime( '2026-09-21 08:30:00' );
teq( 'đối tượng ngày -> yyyy-mm-dd',        '2026-09-21',       VHJP_Doc::ngay( $d ) );
teq( 'đối tượng ngày -> yyyy-mm-dd HH:MM',  '2026-09-21 08:30', VHJP_Doc::ngay_gio( $d ) );

// ============================================================ 7. Cầu nối phải THẬT nạp mã gốc
/* Nếu cầu nối lặng lẽ chép hàm vào chính nó thay vì đọc tệp gốc thì mọi phép trên là so bản
   chép với bản chép. Đục mã gốc một nhát rồi đòi bản gốc đổi theo. */
$tep_goc = $goc . '/goc/jp-capsule-v2/JP2_01_Core.gs';
$luu = file_get_contents( $tep_goc );
file_put_contents( $tep_goc, str_replace(
	'function jpNum_(v) {', 'function jpNum_(v) { return 424242;', $luu, $so_thay ) );
$thu = goc_chay( 'num', array( array( '20000' ) ) );
file_put_contents( $tep_goc, $luu );
t( 'đục được đúng một chỗ trong mã gốc', 1 === $so_thay, $so_thay );
t( '🔴 cầu nối ĐỌC THẬT tệp gốc, không chép hàm vào chính nó',
	is_array( $thu ) && isset( $thu[0] ) && 424242 === $thu[0], $thu );
t( 'và đã trả mã gốc về nguyên trạng', file_get_contents( $tep_goc ) === $luu );

// ============================================================ kết
if ( $truot ) {
	echo "HỎNG: " . count( $truot ) . "\n";
	foreach ( $truot as $x ) { echo "  ✗ $x\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — bản PHP ra y hệt mã JavaScript gốc, kể cả mấy ca có mìn.\n";
