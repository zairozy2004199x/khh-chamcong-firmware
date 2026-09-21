<?php
/**
 * Kiểm phép ghép của đối soát — chạy không cần WordPress, không cần MySQL.
 *
 *   php tools/kh-tai-chinh/tests/kiem-ghep.php
 *
 * Hàm ghép() quyết định một đồng nằm ở nhóm "Khớp" hay nhóm "Thiếu". Ghép sai
 * không làm hỏng gì thấy được: bảng vẫn ra, tổng vẫn cộng, chỉ là kế toán đi
 * đòi cổng một khoản tiền đã về, hoặc bỏ qua một khoản chưa về.
 */

define( 'ABSPATH', __DIR__ );
define( 'DAY_IN_SECONDS', 86400 );
require __DIR__ . '/../wordpress/kh-tai-chinh/includes/class-khtc-doi-soat.php';

$dat  = 0;
$hong = array();

function kiem( $ten, $that, $mong ) {
	global $dat, $hong;
	if ( $that === $mong ) { $dat++; return; }
	$hong[] = sprintf( '%s: nhận %s, mong %s', $ten, var_export( $that, true ), var_export( $mong, true ) );
}

/** Dòng cổng. */
function c( $id, $ngay, $ma, $tien, $phi = 0 ) {
	return (object) array( 'id' => $id, 'ngay' => $ngay, 'ma_gd' => $ma, 'so_tien' => $tien, 'phi' => $phi );
}
/** Dòng sao kê ngân hàng. */
function n( $id, $ngay, $ma, $tien ) {
	return (object) array( 'id' => $id, 'ngay' => $ngay, 'ma_gd' => $ma, 'so_tien' => $tien );
}

$G = array( 'KHTC_DoiSoat', 'ghep' );

// --------------------------------------------------- lượt 1: trùng mã GD
$r = call_user_func( $G, array( c( 1, '2026-08-05', 'PAY1', 1000000 ) ), array( n( 9, '2026-08-07', 'PAY1', 1000000 ) ) );
kiem( 'trùng mã, lệch ngày vẫn khớp', $r[1], array( 9, 'khop_ma' ) );

$r = call_user_func( $G, array( c( 1, '2026-08-05', 'PAY1', 1000000, 11000 ) ), array( n( 9, '2026-08-07', 'PAY1', 989000 ) ) );
kiem( 'trùng mã, ngân hàng về số ròng vẫn khớp', $r[1], array( 9, 'khop_ma' ) );

$r = call_user_func( $G, array( c( 1, '2026-08-05', 'PAY1', 1000000 ) ), array( n( 9, '2026-08-05', 'PAY1', 900000 ) ) );
kiem( 'trùng mã nhưng tiền khác → lệch tiền', $r[1], array( 9, 'lech_tien' ) );

// --------------------------------------------- lượt 2: trùng ngày + tiền
$r = call_user_func( $G, array( c( 1, '2026-08-05', '', 500000 ) ), array( n( 9, '2026-08-05', '', 500000 ) ) );
kiem( 'không mã, trùng ngày và tiền', $r[1], array( 9, 'khop_ngay' ) );

// --------------------------------------------- lượt 3: lệch ngày trong dung sai
$r = call_user_func( $G, array( c( 1, '2026-08-05', '', 500000 ) ), array( n( 9, '2026-08-07', '', 500000 ) ) );
kiem( 'lệch 2 ngày, trong dung sai', $r[1], array( 9, 'khop_lech' ) );

$r = call_user_func( $G, array( c( 1, '2026-08-05', '', 500000 ) ), array( n( 9, '2026-08-20', '', 500000 ) ) );
kiem( 'lệch 15 ngày, ngoài dung sai → không ghép', isset( $r[1] ), false );

// --------------------------------------------- lượt 4: trừ phí
$r = call_user_func( $G, array( c( 1, '2026-08-05', '', 1000000, 11000 ) ), array( n( 9, '2026-08-05', '', 989000 ) ) );
kiem( 'không mã, khớp sau khi trừ phí', $r[1], array( 9, 'khop_tru_phi' ) );

$r = call_user_func( $G, array( c( 1, '2026-08-05', '', 1000000, 0 ) ), array( n( 9, '2026-08-05', '', 989000 ) ) );
kiem( 'phí bằng 0 thì không ghép bừa', isset( $r[1] ), false );

// ------------------------------------------------- không ăn hai lần một dòng
$r = call_user_func(
	$G,
	array( c( 1, '2026-08-05', '', 500000 ), c( 2, '2026-08-05', '', 500000 ) ),
	array( n( 9, '2026-08-05', '', 500000 ) )
);
kiem( 'hai dòng cổng bằng nhau, một dòng sao kê: chỉ một dòng được ghép', count( $r ), 1 );
kiem( 'dòng được ghép là dòng đầu', isset( $r[1] ), true );

// ----------------------------------- lượt chắc hơn giành trước lượt kém chắc
// Dòng cổng #2 khớp đúng ngày với NH #8; dòng #1 chỉ khớp lệch ngày. Nếu chạy
// theo thứ tự dòng thay vì theo lượt, #1 sẽ chiếm mất NH #8 và #2 thành "thiếu".
$r = call_user_func(
	$G,
	array( c( 1, '2026-08-03', '', 700000 ), c( 2, '2026-08-05', '', 700000 ) ),
	array( n( 8, '2026-08-05', '', 700000 ) )
);
kiem( 'khớp đúng ngày được ưu tiên hơn khớp lệch ngày', $r[2], array( 8, 'khop_ngay' ) );
kiem( 'dòng chỉ khớp lệch ngày bị đẩy sang thiếu', isset( $r[1] ), false );

// ---------------------------- mã GD được ưu tiên tuyệt đối so với ngày+tiền
// NH #8 trùng ngày và tiền với cổng #1, nhưng mã của nó là của cổng #2.
$r = call_user_func(
	$G,
	array( c( 1, '2026-08-05', 'PAY1', 300000 ), c( 2, '2026-08-05', 'PAY2', 300000 ) ),
	array( n( 8, '2026-08-05', 'PAY2', 300000 ), n( 9, '2026-08-09', 'PAY1', 300000 ) )
);
kiem( 'mã GD thắng ngày+tiền (dòng 1)', $r[1], array( 9, 'khop_ma' ) );
kiem( 'mã GD thắng ngày+tiền (dòng 2)', $r[2], array( 8, 'khop_ma' ) );

// ------------------------------------------------------------- không ghép
$r = call_user_func( $G, array( c( 1, '2026-08-05', 'PAY1', 1000000 ) ), array() );
kiem( 'sao kê rỗng thì không ghép được gì', $r, array() );

$r = call_user_func( $G, array(), array( n( 9, '2026-08-05', '', 1000000 ) ) );
kiem( 'không có dòng cổng thì kết quả rỗng', $r, array() );

// ---------------------------------------------------------------- cách ngày
kiem( 'cách ngày cùng ngày', KHTC_DoiSoat::cach_ngay( '2026-08-05', '2026-08-05' ), 0 );
kiem( 'cách ngày qua tháng', KHTC_DoiSoat::cach_ngay( '2026-07-31', '2026-08-02' ), 2 );
kiem( 'cách ngày không phụ thuộc thứ tự', KHTC_DoiSoat::cach_ngay( '2026-08-02', '2026-07-31' ), 2 );

// ------------------------------------------- một đợt cỡ thật, kiểm tổng thể
$cong = array();
$nh   = array();
for ( $i = 1; $i <= 200; $i++ ) {
	$ngay   = sprintf( '2026-08-%02d', ( $i % 28 ) + 1 );
	$tien   = 100000 + $i * 1000;
	$cong[] = c( $i, $ngay, 'MA' . $i, $tien, 1100 );
	// 180 dòng về ngân hàng đúng số ròng, 20 dòng cuối chưa về.
	if ( $i <= 180 ) {
		$nh[] = n( 1000 + $i, $ngay, '', $tien - 1100 );
	}
}
$nh[] = n( 9999, '2026-08-15', '', 77777 ); // dòng ngân hàng không ai nhận → thừa
$r    = call_user_func( $G, $cong, $nh );
kiem( 'đợt 200 dòng: ghép đúng 180', count( $r ), 180 );
$kieu = array_unique( array_column( array_values( $r ), 1 ) );
kiem( 'đợt 200 dòng: tất cả đều là khớp sau khi trừ phí', $kieu, array( 'khop_tru_phi' ) );
$da_dung = array_column( array_values( $r ), 0 );
kiem( 'đợt 200 dòng: không dòng sao kê nào bị nhận hai lần', count( array_unique( $da_dung ) ), 180 );
kiem( 'đợt 200 dòng: dòng 77.777 đ không bị ghép nhầm', in_array( 9999, $da_dung, true ), false );

printf( "%d kiểm tra đạt, %d lỗi\n", $dat, count( $hong ) );
foreach ( $hong as $h ) { echo "  ✗ $h\n"; }
exit( $hong ? 1 : 0 );
