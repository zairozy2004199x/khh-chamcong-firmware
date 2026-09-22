<?php
/**
 * KẾ TOÁN DUYỆT JP — `VHJP_Duyet`
 * =============================================================================================
 *
 * 🔴 HOÀN TẤT LÀ MỐC DUY NHẤT ĐƯỢC RA SỔ KHO, VÀ NÓ CẦN ĐỦ HAI CHỮ KÝ.
 *    Anh Andy ký phần HÀNG HOÁ lúc 23:30 rồi báo *"duyệt kho mà không thấy trừ tồn"*. Máy chủ
 *    làm đúng luật; cái sai là APP chỉ nói TRẠNG THÁI chứ không nói HẬU QUẢ, và nói bằng một
 *    dòng thông báo thoáng qua — đóng đi là không còn dấu vết ở đâu. Nên bài này đòi câu chữ
 *    của `ket_ky()` nói ra ĐỦ: đã ký phần nào, ai ký, và CHƯA trừ kho, CHƯA vào sổ công nợ.
 *
 * ---------------------------------------------------------------------------------------------
 * 🔴 HAI CHỖ BẢN NÀY CỐ Ý KHÁC MÃ GỐC — CẢ HAI ĐỀU ĐƯỢC ĐO RIÊNG Ở DƯỚI
 * ---------------------------------------------------------------------------------------------
 *  ①  SỔ KHO HAI TẦNG CHƯA CHUYỂN. Bản gốc lúc HOÀN TẤT thì trừ lớp tồn và ghi giá vốn; lúc
 *     TRẢ VỀ một báo cáo đã hoàn tất thì hoàn kho lại. Bản này chưa có mô-đun ấy, nên đi đúng
 *     đường mà chính bản gốc đã dựng cho tình huống "sổ kho không ghi được": trả `kho` mang
 *     `loi`, kèm việc phải làm. Im lặng ở đây là báo cáo TRÔNG NHƯ ĐÃ XONG mà giá vốn không có
 *     ở đâu cả — đúng loại lỗi tệ nhất.
 *
 *  ②  PHÉP GỘP CẢNH BÁO CỦA BẢN GỐC KHÔNG BAO GIỜ CHẠY. `jpKtGetReport` chép tay sáu trường
 *     của mỗi cảnh báo và BỎ QUÊN `gop`/`so`, mà `jpGopCanhBao_` lại bắt đầu bằng
 *     `if (!w.gop) { ra.push(w); return; }` ⇒ không cảnh báo nào vào được nhánh gộp. Tính năng
 *     anh Andy đặt hàng 16/08/2026 (*"gộp W9 thành một dòng đi em"*) chạy mà không làm gì, và
 *     vẫn xanh vì không ai đếm. Bản này MANG THEO hai trường ấy — cùng lối đã từ chối chép
 *     `slice(-4)` của `jpNextId_`. Phép thử ⑦ đo đúng chỗ khác nhau đó.
 *
 * Chạy: php tools/test/kiem-jp-duyet.php
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

$plg = $goc . '/wordpress/vhcp-jp/includes/';
foreach ( array( 'db', 'doc', 'nguon', 'ma', 'nhat-ky', 'auth', 'cau-hinh', 'tinh', 'anh',
	'bao-cao', 'duyet' ) as $f ) {
	require_once $plg . 'class-vhjp-' . $f . '.php';
}
global $wpdb;
vhcp_stub_dung_bang( VHJP_DB::bang(), $wpdb->prefix . 'vhjp_' );
t( '🔴 dựng được đủ bảng', ! vhcp_stub_loi_ddl(), vhcp_stub_loi_ddl() );

$cau_noi = __DIR__ . '/jp-doc-goc.js';
exec( 'node --version 2>/dev/null', $r_, $ma_node );
t( '🔴 có node để chạy mã gốc', 0 === $ma_node );

function goc( $ten, $ca, $so = null, $ai = null ) {
	global $cau_noi;
	$ra = array(); $ma = 0;
	$lenh = 'node ' . escapeshellarg( $cau_noi ) . ' ' . escapeshellarg( $ten ) . ' '
		. escapeshellarg( wp_json_encode( $ca ) );
	if ( null !== $so ) { $lenh .= ' ' . escapeshellarg( wp_json_encode( $so ) ); }
	if ( null !== $ai ) { $lenh .= ' ' . escapeshellarg( wp_json_encode( $ai ) ); }
	exec( $lenh . ' 2>&1', $ra, $ma );
	if ( 0 !== $ma ) { return array( 'loi' => implode( "\n", $ra ) ); }
	return json_decode( implode( '', $ra ), true );
}
function so_tu_csdl() {
	$ra = array();
	foreach ( array( 'JP_Items', 'JP_Locations', 'JP_Clusters', 'JP_Machines',
		'JP_Reports', 'JP_Zones', 'JP_Rows', 'JP_Photos' ) as $tab ) {
		$ra[ $tab ] = VHJP_Nguon::doc( $tab );
	}
	return $ra;
}
function chieu( $nhan, $g, $p, $bo_qua = array() ) {
	if ( ! is_array( $g ) || ! is_array( $p ) ) {
		t( "$nhan · cùng kiểu", wp_json_encode( $g ) === wp_json_encode( $p ),
			'gốc ' . wp_json_encode( $g ) . ' · PHP ' . wp_json_encode( $p ) );
		return;
	}
	foreach ( array_unique( array_merge( array_keys( $g ), array_keys( $p ) ) ) as $k ) {
		if ( in_array( $k, $bo_qua, true ) ) { continue; }
		$a = array_key_exists( $k, $g ) ? $g[ $k ] : '«THIẾU»';
		$b = array_key_exists( $k, $p ) ? $p[ $k ] : '«THIẾU»';
		t( "$nhan · $k", wp_json_encode( $a ) === wp_json_encode( $b ),
			'gốc ' . wp_json_encode( $a ) . ' · PHP ' . wp_json_encode( $b ) );
	}
}

// ============================================================ 1. TÌNH TRẠNG CHỮ KÝ
$ca_ket = array(
	array( array( 'status' => 'CHO_DUYET' ) ),                                  // chưa ai ký
	array( array( 'status' => 'CHO_DUYET', 'apprRevBy' => 'Chị A',
		'apprRevAt' => '2026-09-20 10:00' ) ),                                   // 🔴 ký MỘT phần
	array( array( 'status' => 'CHO_DUYET', 'apprStockBy' => 'Chị A',
		'apprStockAt' => '2026-09-20 10:00' ) ),                                 // 🔴 ký một phần kia
	array( array( 'status' => 'HOAN_TAT', 'apprRevBy' => 'A', 'apprStockBy' => 'A' ) ),
	array( array( 'status' => 'CHO_DUYET', 'apprBy' => 'A', 'apprAt' => '2026-09-20 10:00' ) ),
	array( null ),
);
$g = goc( 'ket_ky', $ca_ket, array() );
foreach ( $ca_ket as $i => $ca ) {
	chieu( "ket_ky[$i]", isset( $g[ $i ] ) ? $g[ $i ] : null, VHJP_Duyet::ket_ky( $ca[0] ) );
}
$mot = VHJP_Duyet::ket_ky( array( 'status' => 'CHO_DUYET', 'apprStockBy' => 'Chị A',
	'apprStockAt' => '2026-09-20 10:00' ) );
teq( '🔴 ký ĐÚNG MỘT phần là trạng thái KẸT', true, $mot['motPhan'] );
t( '🔴 và câu chữ nói ra HẬU QUẢ, không chỉ nói trạng thái',
	false !== mb_strpos( $mot['msg'], 'CHƯA trừ kho' )
		&& false !== mb_strpos( $mot['msg'], 'CHƯA vào sổ công nợ' ), $mot['msg'] );
t( 'và nói rõ ai đã ký, ký lúc nào',
	false !== mb_strpos( $mot['msg'], 'Chị A' ) && false !== mb_strpos( $mot['msg'], '10:00' ), $mot['msg'] );
t( 'và còn thiếu phần nào', false !== mb_strpos( $mot['msg'], 'DOANH THU' ), $mot['msg'] );
teq( '🔴 chưa ai ký thì KHÔNG phải trạng thái kẹt — đó là chuyện bình thường', false,
	VHJP_Duyet::ket_ky( array( 'status' => 'CHO_DUYET' ) )['motPhan'] );
teq( 'và đã hoàn tất cũng không', false,
	VHJP_Duyet::ket_ky( array( 'status' => 'HOAN_TAT', 'apprRevBy' => 'A', 'apprStockBy' => 'A' ) )['motPhan'] );
teq( '🔴 chữ ký GỘP đời cũ tính là ký CẢ HAI ⇒ cũng không kẹt', false,
	VHJP_Duyet::ket_ky( array( 'status' => 'CHO_DUYET', 'apprBy' => 'A' ) )['motPhan'] );

// ============================================================ 2. KÝ ĐƯỢC HAY KHÔNG
$ca_ky = array(
	array( array(), array( 'status' => 'CHO_DUYET' ), 'REV' ),
	array( array(), array( 'status' => 'CHO_DUYET', 'apprRevBy' => 'A' ), 'REV' ),
	array( array(), array( 'status' => 'CHO_DUYET', 'apprRevBy' => 'A' ), 'STOCK' ),
	array( array(), array( 'status' => 'CAN_SUA' ), 'REV' ),
	array( array(), array( 'status' => 'HOAN_TAT' ), 'REV' ),
	array( array(), array( 'status' => 'NHAP' ), 'REV' ),
);
$g = goc( 'ky_duoc', $ca_ky, array() );
foreach ( $ca_ky as $i => $ca ) {
	teq( "ky_duoc[$i]", isset( $g[ $i ] ) ? $g[ $i ] : null,
		VHJP_Duyet::ky_duoc( $ca[1], $ca[2] ) );
}
teq( '🔴 CAN_SUA thì KHÔNG ký — đang chờ nhân viên nộp lại, số sắp đổi', false,
	VHJP_Duyet::ky_duoc( array( 'status' => 'CAN_SUA' ), 'REV' ) );

// ============================================================ 3. SỔ THỬ
$SO = array(
	'JP_Items' => array(
		array( 'code' => '100JP031', 'misa' => 'MS031', 'name' => 'Trứng 100k', 'price' => 0,
			'dvt' => '', 'active' => 'Y' ),
	),
	'JP_Locations' => array(
		array( 'id' => 'L1', 'code' => 'AMBD', 'name' => 'AEON Bình Dương', 'maKH' => 'KH00119',
			'machineType' => 'TIEN', 'bcMau' => '', 'coDhTrung' => '', 'chonGiaXung' => '',
			'active' => 'Y' ),
	),
	'JP_Clusters' => array(
		array( 'id' => 'C1', 'locationId' => 'L1', 'name' => 'Cụm cửa chính', 'hasQR' => 'Y',
			'photoCount' => 1, 'active' => 'Y' ),
	),
	'JP_Machines' => array(
		array( 'id' => 'M1', 'locationId' => 'L1', 'clusterId' => 'C1', 'code' => 'MAY-01',
			'itemCode' => '100JP031', 'photoCount' => 1, 'active' => 'Y' ),
		array( 'id' => 'M2', 'locationId' => 'L1', 'clusterId' => 'C1', 'code' => 'MAY-02',
			'itemCode' => '100JP031', 'photoCount' => 1, 'active' => 'Y' ),
	),
	'JP_Reports' => array(
		/* Đã nộp, chưa ai ký — có cảnh báo thiếu ảnh đã chốt. */
		array( 'id' => 'RP-A', 'locationId' => 'L1', 'locationName' => 'AEON Bình Dương',
			'maKH' => 'KH00119', 'machineType' => 'TIEN', 'bcMau' => '',
			'fromDate' => '2026-09-01', 'toDate' => '2026-09-15', 'userId' => 'U1',
			'userName' => 'Nam', 'status' => 'CHO_DUYET', 'revMeter' => 1000000,
			/* ⚠️ Hoàn khách mà CHƯA ghi lý do — cố ý, để báo cáo này CÓ cảnh báo CẤP BÁO CÁO
			   (W6). Không có nó thì phép "màn duyệt phải thấy cả cảnh báo cấp báo cáo" đo
			   trên một danh sách rỗng, tức không đo gì. */
			'revBank' => 200000, 'adjMachine' => 0, 'refundCustomer' => 50000,
			'refundNote' => '', 'refundRows' => 0, 'cashActual' => 750000,
			'totalSubmit' => 950000, 'warnCount' => 3,
			'photoWarnJson' => '[{"code":"W7","part":"STOCK","msg":"Thiếu ảnh so với cấu hình","detail":"Ô MAY-01: thiếu 1 ảnh chỉ số"}]' ),
		/* Đã ký MỘT phần — trạng thái KẸT. */
		array( 'id' => 'RP-B', 'locationId' => 'L1', 'locationName' => 'AEON Bình Dương',
			'machineType' => 'TIEN', 'bcMau' => '', 'fromDate' => '2026-08-01',
			'toDate' => '2026-08-15', 'userId' => 'U1', 'userName' => 'Nam',
			'status' => 'CHO_DUYET', 'apprStockBy' => 'Chị Kế Toán',
			'apprStockAt' => '2026-08-20 09:00:00', 'totalSubmit' => 500000, 'warnCount' => 0 ),
		/* Còn NHÁP — kế toán KHÔNG được thấy. */
		array( 'id' => 'RP-NHAP', 'locationId' => 'L1', 'machineType' => 'TIEN', 'bcMau' => '',
			'fromDate' => '2026-07-01', 'toDate' => '2026-07-15', 'userId' => 'U1',
			'userName' => 'Nam', 'status' => 'NHAP' ),
		/* 🔴 Đang CẦN SỬA — kế toán không được ký lên bản nhân viên đang sửa dở. Và nó mang
		   chữ ký GỘP đời cũ, để đo phép "trả về thì xoá sạch mọi chữ ký". */
		array( 'id' => 'RP-SUA', 'locationId' => 'L1', 'machineType' => 'TIEN', 'bcMau' => '',
			'fromDate' => '2026-05-01', 'toDate' => '2026-05-15', 'userId' => 'U1',
			'userName' => 'Nam', 'status' => 'CAN_SUA', 'apprBy' => 'Ai Đó',
			'apprAt' => '2026-05-20 09:00:00', 'rejectReason' => 'lần trước sai' ),
		/* Đã HOÀN TẤT. */
		array( 'id' => 'RP-XONG', 'locationId' => 'L1', 'machineType' => 'TIEN', 'bcMau' => '',
			'fromDate' => '2026-06-01', 'toDate' => '2026-06-15', 'userId' => 'U1',
			'userName' => 'Nam', 'status' => 'HOAN_TAT', 'apprRevBy' => 'A',
			'apprRevAt' => '2026-06-20 09:00:00', 'apprStockBy' => 'A',
			'apprStockAt' => '2026-06-20 09:01:00' ),
	),
	/* ⚠️ NẠP SAU CÙNG nhưng KỲ MỚI NHẤT — cố ý. Nếu thứ tự nạp vào sổ đã trùng với thứ tự
	   phải hiện ra thì phép "xếp mới nhất trước" xanh mà không đo gì. */
	'JP_Reports_them' => array(
		array( 'id' => 'RP-MOI', 'locationId' => 'L1', 'locationName' => 'AEON Bình Dương',
			'machineType' => 'TIEN', 'bcMau' => '', 'fromDate' => '2026-10-01',
			'toDate' => '2026-10-15', 'userId' => 'U1', 'userName' => 'Nam',
			'status' => 'CHO_DUYET', 'totalSubmit' => 100000 ),
	),
	'JP_Zones' => array(
		array( 'id' => 'RP-A-Z1', 'reportId' => 'RP-A', 'seq' => 1, 'name' => 'Tầng 1',
			'clusterId' => 'C1', 'note' => '' ),
	),
	'JP_Rows' => array(
		/* Hai dòng cùng sinh cảnh báo W9 (dư tiền lẻ) — đúng ca mà phép GỘP sinh ra để xử lý. */
		array( 'id' => 'RP-A-R1', 'reportId' => 'RP-A', 'zoneId' => 'RP-A-Z1', 'seq' => 1,
			'rowKind' => 'MONEY', 'machineId' => 'M1', 'machineCode' => 'MAY-01',
			'itemCode' => '100JP031', 'price' => 100000, 'mBefore' => 100, 'mAfter' => 103,
			'hBefore' => 30, 'hAfter' => 29, 'stockActual' => 29, 'bank' => 0,
			'warnJson' => '[{"code":"W9","part":"REV","msg":"Tiền không chia hết cho giá 1 trứng","detail":"x","gop":"DU_TIEN","so":15000},{"code":"W14","part":"REV","msg":"Mã hàng không suy ra được giá","detail":"y"}]' ),
		array( 'id' => 'RP-A-R2', 'reportId' => 'RP-A', 'zoneId' => 'RP-A-Z1', 'seq' => 2,
			'rowKind' => 'MONEY', 'machineId' => 'M2', 'machineCode' => 'MAY-02',
			'itemCode' => '100JP031', 'price' => 100000, 'mBefore' => 200, 'mAfter' => 203,
			'hBefore' => 40, 'hAfter' => 39, 'stockActual' => 39, 'bank' => 0,
			'warnJson' => '[{"code":"W9","part":"REV","msg":"Tiền không chia hết cho giá 1 trứng","detail":"x","gop":"DU_TIEN","so":5000}]' ),
	),
	'JP_Photos' => array(),
);
foreach ( $SO as $tab => $ds ) {
	$that = ( 'JP_Reports_them' === $tab ) ? 'JP_Reports' : $tab;
	foreach ( $ds as $hang ) {
		$k = isset( $hang['id'] ) ? $hang['id'] : $hang['code'];
		t( "nạp được $that#$k", false !== VHJP_Nguon::them( $that, $hang ), VHJP_Nguon::loi_cuoi() );
	}
}
unset( $SO['JP_Reports_them'] );
$KT = array( 'id' => 'U9', 'hoTen' => 'Chị Kế Toán', 'role' => 'KETOAN', 'machineType' => '',
	'locationIds' => array() );
$KT2 = array( 'id' => 'U8', 'hoTen' => 'Anh Kế Toán Hai', 'role' => 'KETOAN',
	'machineType' => '', 'locationIds' => array() );

// ============================================================ 4. DANH SÁCH CHỜ DUYỆT
$g = goc( 'ds_bao_cao', array( array( 'the', array() ) ), so_tu_csdl(), $KT );
$ds_goc = isset( $g[0] ) ? $g[0] : $g;
$ds_php = VHJP_Duyet::ds_bao_cao( $KT, array() );
teq( 'danh sách · đúng số lượng', count( $ds_goc ), count( $ds_php ) );
foreach ( $ds_goc as $i => $r ) { chieu( "danh sách[$i]", $r, $ds_php[ $i ] ); }

$ma_ds = array_column( $ds_php, 'id' );
t( '🔴 kế toán KHÔNG thấy bản NHÁP của nhân viên', ! in_array( 'RP-NHAP', $ma_ds, true ), $ma_ds );
teq( '🔴 xếp MỚI NHẤT TRƯỚC — và bản mới nhất là bản nạp SAU CÙNG vào sổ',
	array( 'RP-MOI', 'RP-A', 'RP-B', 'RP-XONG', 'RP-SUA' ), array_column( $ds_php, 'id' ) );
$a = null;
foreach ( $ds_php as $r ) { if ( 'RP-A' === $r['id'] ) { $a = $r; } }
teq( '🔴 số cảnh báo trên thẻ CỘNG CẢ thiếu ảnh (3 + 1)', 4, $a['warnCount'] );
teq( 'và tổng hoàn tính lại từ sổ', 50000, $a['refundTotal'] );
teq( 'chưa ai ký thì ký được cả hai phần', true, $a['canSignRev'] && $a['canSignStock'] );

$b = null;
foreach ( $ds_php as $r ) { if ( 'RP-B' === $r['id'] ) { $b = $r; } }
teq( '🔴 báo cáo ký một phần: phần đã ký thì thôi ký lại', false, $b['canSignStock'] );
teq( 'phần kia vẫn ký được', true, $b['canSignRev'] );
t( '🔴 và mang theo dải băng nói rõ đang KẸT', $b['ketKy']['motPhan'], $b['ketKy'] );

/* Bộ lọc. */
foreach ( array(
	array( 'status' => 'HOAN_TAT' ),
	array( 'locationId' => 'L1' ),
	array( 'onlyMine' => true ),
	array( 'motPhan' => true ),
	array( 'fromDate' => '2026-09-01' ),
	array( 'toDate' => '2026-08-31' ),
) as $f ) {
	$g = goc( 'ds_bao_cao', array( array( 'the', $f ) ), so_tu_csdl(), $KT );
	$gg = isset( $g[0] ) ? $g[0] : array();
	teq( 'lọc ' . json_encode( $f ) . ' · đúng bộ mã', array_column( $gg, 'id' ),
		array_column( VHJP_Duyet::ds_bao_cao( $KT, $f ), 'id' ) );
}
teq( '🔴 lọc "đang KẸT" chỉ ra báo cáo ký ĐÚNG MỘT phần', array( 'RP-B' ),
	array_column( VHJP_Duyet::ds_bao_cao( $KT, array( 'motPhan' => true ) ), 'id' ) );
t( '🔴 còn "thiếu chữ ký" thì gồm CẢ báo cáo chưa ai ký — hai bộ lọc khác nhau',
	in_array( 'RP-A', array_column( VHJP_Duyet::ds_bao_cao( $KT, array( 'onlyMine' => true ) ), 'id' ), true ) );

// ============================================================ 5. MÀN CHI TIẾT
$g = goc( 'lay_kt', array( array( 'the', 'RP-A' ) ), so_tu_csdl(), $KT );
$ct_goc = isset( $g[0] ) ? $g[0] : $g;
$ct_php = VHJP_Duyet::lay( $KT, 'RP-A' );
t( '🔴 mã gốc mở được màn chi tiết', is_array( $ct_goc ) && ! isset( $ct_goc['loi'] ), $ct_goc );

if ( is_array( $ct_goc ) && ! isset( $ct_goc['loi'] ) ) {
	foreach ( array( 'canSignRev', 'canSignStock', 'warnGoc' ) as $k ) {
		teq( "chi tiết · $k", $ct_goc[ $k ], $ct_php[ $k ] );
	}
	chieu( 'chi tiết · signState', $ct_goc['signState'], $ct_php['signState'] );
	chieu( 'chi tiết · ketKy', $ct_goc['ketKy'], $ct_php['ketKy'] );
	chieu( 'chi tiết · tienVsHang', $ct_goc['tienVsHang'], (array) $ct_php['tienVsHang'] );
}
teq( '🔴 kế toán nhận ĐỦ dòng, không bị cắt theo phần', 2, count( $ct_php['rows'] ) );
teq( 'và có cả bảng tiền-so-hàng để soát', true, is_array( $ct_php['tienVsHang'] ) );

// ============================================================ 6. GỘP CẢNH BÁO
$ca_gop = array(
	array( array(
		array( 'code' => 'W9', 'part' => 'REV', 'msg' => 'm', 'detail' => 'a', 'gop' => 'DU_TIEN', 'so' => 15000 ),
		array( 'code' => 'W2', 'part' => 'STOCK', 'msg' => 'n', 'detail' => 'b' ),
		array( 'code' => 'W9', 'part' => 'REV', 'msg' => 'm', 'detail' => 'c', 'gop' => 'DU_TIEN', 'so' => 5000 ),
	) ),
	/* Gộp mà chỉ có MỘT dòng. */
	array( array( array( 'code' => 'W9', 'part' => 'REV', 'msg' => 'm', 'detail' => 'a',
		'gop' => 'DU_TIEN', 'so' => 1000 ) ) ),
	/* 🔴 W14 dùng chung phần với W9 nhưng KHÔNG có `gop` ⇒ phải đứng riêng. */
	array( array(
		array( 'code' => 'W9', 'part' => 'REV', 'msg' => 'm', 'detail' => 'a', 'gop' => 'DU_TIEN', 'so' => 1000 ),
		array( 'code' => 'W14', 'part' => 'REV', 'msg' => 'k', 'detail' => 'd' ),
	) ),
	array( array() ),
);
$g = goc( 'gop_canh_bao', $ca_gop, array() );
foreach ( $ca_gop as $i => $ca ) {
	$gg = isset( $g[ $i ] ) ? $g[ $i ] : null;
	$pp = VHJP_Tinh::gop_canh_bao( $ca[0] );
	teq( "gop_canh_bao[$i] · số dòng ra", is_array( $gg ) ? count( $gg ) : null, count( $pp ) );
	foreach ( (array) $gg as $j => $w ) { chieu( "gop_canh_bao[$i][$j]", $w, $pp[ $j ] ); }
}
$gop1 = VHJP_Tinh::gop_canh_bao( $ca_gop[0][0] );
teq( '🔴 hai dòng W9 thu về MỘT', 2, count( $gop1 ) );
teq( 'và cộng đúng tổng dư', 20000, $gop1[0]['tong'] );
t( 'câu chữ nói rõ mấy dòng và tổng',
	false !== mb_strpos( $gop1[0]['detail'], '2 dòng' )
		&& false !== mb_strpos( $gop1[0]['detail'], '20.000' ), $gop1[0]['detail'] );
teq( '🔴 GIỮ ĐÚNG CHỖ cái đầu tiên xuất hiện, không dồn nhóm xuống cuối', 'W9', $gop1[0]['code'] );
teq( 'và W2 vẫn đứng riêng đúng thứ tự', 'W2', $gop1[1]['code'] );
$gop3 = VHJP_Tinh::gop_canh_bao( $ca_gop[2][0] );
teq( '🔴 W14 KHÔNG bị gộp vào W9 — gộp là giấu mất một đường kiểm', 2, count( $gop3 ) );

/* 🔴 CHỖ CỐ Ý KHÁC MÃ GỐC — xem khối ② ở đầu tệp. */
$co_gop = 0;
foreach ( $ct_php['warnings'] as $w ) { if ( ! empty( $w['gop'] ) ) { $co_gop++; } }
teq( '🔴 màn chi tiết CÓ gộp thật: 3 cảnh báo dòng thu còn 2', 2, $co_gop + 1 );
/* 5 cảnh báo thật = 3 của dòng (W9 · W14 · W9) + 1 cấp báo cáo (W6) + 1 thiếu ảnh (W7). */
teq( 'số cảnh báo THẬT vẫn được nói ra', 5, $ct_php['warnGoc'] );
teq( 'còn sau khi gộp thì ngắn hơn đúng một dòng', 4, count( $ct_php['warnings'] ) );
if ( is_array( $ct_goc ) && isset( $ct_goc['warnings'] ) ) {
	$goc_gop = 0;
	foreach ( $ct_goc['warnings'] as $w ) { if ( ! empty( $w['gop'] ) ) { $goc_gop++; } }
	teq( '🔴 BẰNG CHỨNG: mã gốc KHÔNG gộp được dòng nào (quên mang `gop` sang)', 0, $goc_gop );
	teq( 'nên danh sách của nó dài hơn đúng một dòng',
		count( $ct_php['warnings'] ) + 1, count( $ct_goc['warnings'] ) );
}
$ma_cb = array_column( $ct_php['warnings'], 'code' );
t( 'và cảnh báo thiếu ảnh đã chốt cũng có mặt', in_array( 'W7', $ma_cb, true ), $ma_cb );
t( '🔴 cảnh báo CẤP BÁO CÁO cũng phải tới được màn duyệt — người sắp KÝ cần thấy nó',
	in_array( 'W6', $ma_cb, true ), $ma_cb );

/* 🔴 Và con số TỔNG DƯ phải đi được tới đây. Quên mang trường `so` sang thì phép gộp vẫn
   chạy, danh sách vẫn ngắn lại, chỉ có tổng ra 0 — một con số sai trông y như số đúng. */
$w9 = null;
foreach ( $ct_php['warnings'] as $w ) { if ( 'W9' === $w['code'] ) { $w9 = $w; } }
t( '🔴 dòng W9 đã gộp mang đúng TỔNG DƯ (15.000 + 5.000)',
	is_array( $w9 ) && 20000 === VHJP_Doc::num( $w9['tong'] ), $w9 );
t( 'và câu chữ in ra con số ấy',
	is_array( $w9 ) && false !== mb_strpos( $w9['detail'], '20.000' ), $w9 );

// ============================================================ 7. 🔴 KÝ
/**
 * ⚠️ BỎ GIÂY TRƯỚC KHI SO — khác biệt này ở TẦNG LƯU TRỮ, không ở phép tính.
 *
 * Bên Sheets, thời điểm ký là một đối tượng ngày và được in ra theo khuôn `yyyy-MM-dd HH:mm`.
 * Bên này cột `DATETIME` của MySQL đọc lại là chuỗi ĐÃ CÓ SẴN giây, và `ngay_gio()` chép đúng
 * bản gốc (chuỗi thì trả nguyên văn) nên giây đi theo tới câu thông báo.
 *
 * Hai chuỗi chỉ cùng một khoảnh khắc, khác nhau đúng hai chữ số. KHÔNG sửa `ngay_gio()` cho
 * "đẹp": với chuỗi vào thì bản gốc trả NGUYÊN VĂN, cắt bớt là lệch khỏi mã gốc ở đúng cái hàm
 * mà `kiem-jp-doc.php` đang canh từng ca một. Chỗ lệch nằm ở kiểu cột, và nó chỉ đổi cách
 * HIỆN RA — không con số nào đổi.
 */
function bo_giay( $v ) {
	if ( is_string( $v ) ) {
		return preg_replace( '/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}):\d{2}/', '$1', $v );
	}
	if ( is_array( $v ) ) {
		$ra = array();
		foreach ( $v as $k => $x ) { $ra[ $k ] = bo_giay( $x ); }
		return $ra;
	}
	return $v;
}

$so_truoc = so_tu_csdl();
$ky1 = VHJP_Duyet::ky( $KT, 'RP-A', 'REV', 'ổn' );
$g = goc( 'ky', array( array( 'the', 'RP-A', 'REV', 'ổn' ) ), $so_truoc, $KT );
chieu( 'ký phần đầu · khớp mã gốc', bo_giay( isset( $g[0] ) ? $g[0] : $g ), bo_giay( $ky1 ),
	array( 'kho' ) );

teq( 'ký xong phần đầu thì CHƯA hoàn tất', false, $ky1['done'] );
t( '🔴 và câu thông báo nói ra HẬU QUỐC: chưa trừ kho, chưa vào sổ công nợ',
	false !== mb_strpos( $ky1['msg'], 'CHƯA trừ kho' ), $ky1['msg'] );
teq( '🔴 chưa hoàn tất thì KHÔNG đụng sổ kho', null, $ky1['kho'] );
$sau1 = VHJP_Nguon::tim_mot( 'JP_Reports', 'id', 'RP-A' );
teq( 'chữ ký doanh thu đã vào sổ', 'Chị Kế Toán', VHJP_Doc::str( $sau1['apprRevBy'] ) );
teq( 'và báo cáo VẪN ở CHỜ DUYỆT', 'CHO_DUYET', VHJP_Doc::str( $sau1['status'] ) );

/* Ký lại chính phần ấy: KHÔNG ném, chỉ kể chuyện đã xong. */
$lai = VHJP_Duyet::ky( $KT2, 'RP-A', 'REV' );
t( '🔴 ký lại phần đã ký thì KHÔNG ném — bấm hai lần vì mạng chậm là chuyện thường',
	! empty( $lai['ok'] ) && false !== mb_strpos( $lai['msg'], 'Chị Kế Toán' ), $lai );
teq( 'và KHÔNG ghi đè tên người ký trước', 'Chị Kế Toán',
	VHJP_Doc::str( VHJP_Nguon::tim_mot( 'JP_Reports', 'id', 'RP-A' )['apprRevBy'] ) );

/* Ký nốt phần hai ⇒ HOÀN TẤT. */
$ky2 = VHJP_Duyet::ky( $KT2, 'RP-A', 'STOCK' );
teq( '🔴 đủ hai chữ ký ⇒ HOÀN TẤT', true, $ky2['done'] );
teq( 'và trạng thái vào sổ', 'HOAN_TAT',
	VHJP_Doc::str( VHJP_Nguon::tim_mot( 'JP_Reports', 'id', 'RP-A' )['status'] ) );
/* 🔴 CHỖ CỐ Ý KHÁC MÃ GỐC — xem khối ① ở đầu tệp. */
t( '🔴 HOÀN TẤT mà sổ kho chưa chuyển ⇒ PHẢI NÓI RA, không im lặng',
	is_array( $ky2['kho'] ) && ! empty( $ky2['kho']['loi'] ), $ky2 );
t( '🔴 câu thông báo nói CẢ hậu quả (chưa trừ tồn, chưa có giá vốn) LẪN việc phải làm',
	false !== mb_strpos( $ky2['msg'], 'CHƯA trừ lớp tồn' )
		&& false !== mb_strpos( $ky2['msg'], 'giá vốn' )
		&& false !== mb_strpos( $ky2['msg'], 'chạy lại' ), $ky2['msg'] );

/* Ký một báo cáo đã hoàn tất. */
$xong = VHJP_Duyet::ky( $KT, 'RP-XONG', 'REV' );
teq( '🔴 báo cáo đã hoàn tất thì kể chuyện đã xong, không ném', 'Đã hoàn tất trước đó', $xong['msg'] );

$nem = function ( $f ) { try { $f(); return ''; } catch ( Throwable $e ) { return $e->getMessage(); } };
teq( 'báo cáo còn NHÁP thì chưa ký được', 'Báo cáo chưa nộp',
	$nem( function () use ( $KT ) { VHJP_Duyet::ky( $KT, 'RP-NHAP', 'REV' ); } ) );
teq( '🔴 báo cáo đang CẦN SỬA thì KHÔNG ký — số sắp đổi dưới tay người ký',
	'Báo cáo đang chờ nhân viên sửa',
	$nem( function () use ( $KT ) { VHJP_Duyet::ky( $KT, 'RP-SUA', 'REV' ); } ) );
teq( 'không tìm thấy thì nói rõ', 'Không tìm thấy báo cáo',
	$nem( function () use ( $KT ) { VHJP_Duyet::ky( $KT, 'KHONGCO', 'REV' ); } ) );

$nk = VHJP_Nguon::tim( 'JP_Audit', 'action', 'APPROVE_REV' );
t( '🔴 có ghi nhật ký lượt ký', count( $nk ) > 0, count( $nk ) );

// ============================================================ 8. 🔴 TRẢ VỀ
teq( 'trả về mà không có lý do thì chối', 'Nhập lý do trả về',
	$nem( function () use ( $KT ) { VHJP_Duyet::tra_ve( $KT, 'RP-B', '' ); } ) );

$tv = VHJP_Duyet::tra_ve( $KT, 'RP-B', 'Chỉ số máy 2 sai, xem lại ảnh' );
$sau_b = VHJP_Nguon::tim_mot( 'JP_Reports', 'id', 'RP-B' );
teq( 'trạng thái về CẦN SỬA', 'CAN_SUA', VHJP_Doc::str( $sau_b['status'] ) );
teq( 'và ghi lại lý do cho nhân viên đọc', 'Chỉ số máy 2 sai, xem lại ảnh',
	VHJP_Doc::str( $sau_b['rejectReason'] ) );
teq( 'kèm người trả về', 'Chị Kế Toán', VHJP_Doc::str( $sau_b['rejectBy'] ) );
teq( '🔴 XOÁ SẠCH chữ ký — giữ một nửa là phần ấy hoàn tất mà không ai soát lần hai',
	'', VHJP_Doc::str( $sau_b['apprStockBy'] ) );
teq( 'cả chữ ký doanh thu', '', VHJP_Doc::str( $sau_b['apprRevBy'] ) );
teq( 'cả chữ ký gộp đời cũ', '', VHJP_Doc::str( $sau_b['apprBy'] ) );
teq( '🔴 báo cáo CHƯA từng hoàn tất thì không có gì phải hoàn kho', null, $tv['kho'] );

/* 🔴 Chữ ký GỘP đời cũ cũng phải bị xoá — giữ nó lại là nó HỒI SINH cả hai phần vừa huỷ. */
$tv_sua = VHJP_Duyet::tra_ve( $KT, 'RP-SUA', 'soát lại' );
$sau_sua = VHJP_Nguon::tim_mot( 'JP_Reports', 'id', 'RP-SUA' );
teq( '🔴 trả về thì chữ ký GỘP đời cũ cũng bị xoá', '', VHJP_Doc::str( $sau_sua['apprBy'] ) );
teq( 'và tình trạng chữ ký đọc ra là CHƯA AI KÝ', false,
	VHJP_BaoCao::chu_ky( $sau_sua )['duCaHai'] );

/* 🔴 GHI HỎNG PHẢI NÓI RA. Trả "đã ký" cho một chữ ký chưa vào sổ là kế toán đi làm việc
   khác, và báo cáo nằm lại mãi ở trạng thái chờ mà không ai biết. */
$wpdb->exec_raw( 'CREATE TRIGGER vhjp_chan_ky BEFORE UPDATE ON ' . VHJP_Nguon::bang( 'JP_Reports' )
	. " BEGIN SELECT RAISE(ABORT, 'o cung hong'); END" );
$loi_ky = $nem( function () use ( $KT ) { VHJP_Duyet::ky( $KT, 'RP-MOI', 'REV' ); } );
$wpdb->exec_raw( 'DROP TRIGGER vhjp_chan_ky' );
t( '🔴 ghi chữ ký hỏng thì NÉM, không gật đầu',
	false !== mb_strpos( $loi_ky, 'Không ghi được chữ ký' ), $loi_ky );
teq( 'và không để lại chữ ký ma trong sổ', '',
	VHJP_Doc::str( VHJP_Nguon::tim_mot( 'JP_Reports', 'id', 'RP-MOI' )['apprRevBy'] ) );

/* Trả về một báo cáo ĐÃ HOÀN TẤT — đây mới là ca phải hoàn kho. */
$tv2 = VHJP_Duyet::tra_ve( $KT, 'RP-A', 'Soát lại tồn cuối kỳ' );
t( '🔴 trả về báo cáo ĐÃ HOÀN TẤT ⇒ phải NÓI RA là sổ kho chưa hoàn được',
	is_array( $tv2['kho'] ) && ! empty( $tv2['kho']['loi'] ), $tv2 );
t( '🔴 và nói rõ hậu quả: sổ 632 còn dòng của nó, lớp tồn còn thiếu số đã trừ',
	false !== mb_strpos( $tv2['msg'], 'sổ 632' )
		&& false !== mb_strpos( $tv2['msg'], 'lớp tồn' ), $tv2['msg'] );
teq( 'báo cáo còn NHÁP thì không trả về được', 'Báo cáo chưa nộp',
	$nem( function () use ( $KT ) { VHJP_Duyet::tra_ve( $KT, 'RP-NHAP', 'x' ); } ) );
t( 'có ghi nhật ký lượt trả về', count( VHJP_Nguon::tim( 'JP_Audit', 'action', 'REJECT' ) ) > 0 );

// ================================== 8b. 🔴 TIỀN SO VỚI HÀNG — BA CHỖ DỄ KẾT LUẬN SAI
/*
 * ①  MÁY XU KHÔNG TRỪ HOÀN KHÁCH. Hai vế của máy xu là cùng một hàm tuyến tính của cùng
 *    những con xu; trừ hoàn vào một vế là TỰ TẠO RA một khoản lệch không có thật, đúng bằng
 *    tiền hoàn, trên MỌI báo cáo máy xu có hoàn khách.
 * ②  KHÔNG CÓ DÒNG HÀNG ⇒ trả `null` để giao diện ẩn hẳn khối. In một bảng toàn số 0 rồi kết
 *    luận "khớp" là dạy kế toán tin vào một phép so không hề diễn ra.
 * ③  HAI ĐƯỜNG HẾT ĐỘC LẬP ⇒ cũng trả `null`. Ô nào chưa nhập đồng hồ đếm trứng thì số bán
 *    của nó SUY RA TỪ CHÍNH SỐ TIỀN — so lại thì lúc nào cũng khớp mà không chứng minh gì.
 */
$ca_tvh = array(
	/* ① máy XU có hoàn khách */
	array( array( 'machineType' => 'XU', 'bcMau' => '', 'refundCustomer' => 100000 ),
		array( array( 'rowKind' => 'COIN', 'amount' => 1000000 ),
			array( 'rowKind' => 'STOCK', 'itemCode' => '100JP031', 'itemMisa' => 'MS031',
				'soldQty' => 10, 'giaXu' => 50000 ) ) ),
	/* máy TIỀN có hoàn khách — CHIỀU NGƯỢC LẠI, phải TRỪ */
	array( array( 'machineType' => 'TIEN', 'bcMau' => '', 'refundCustomer' => 100000 ),
		array( array( 'rowKind' => 'MONEY', 'amount' => 1000000, 'soldQty' => 9,
			'price' => 100000, 'hBefore' => 10, 'hAfter' => 1 ) ) ),
	/* ② không có dòng hàng nào */
	array( array( 'machineType' => 'TIEN', 'bcMau' => '' ), array() ),
	array( array( 'machineType' => 'XU', 'bcMau' => '' ),
		array( array( 'rowKind' => 'COIN', 'amount' => 1000 ) ) ),
	/* ③ mọi ô đều chưa nhập đồng hồ đếm trứng */
	array( array( 'machineType' => 'TIEN', 'bcMau' => '' ),
		array( array( 'rowKind' => 'MONEY', 'amount' => 1000, 'hBefore' => 1, 'hAfter' => '' ) ) ),
	/* một ô có, một ô không ⇒ VẪN so được */
	array( array( 'machineType' => 'TIEN', 'bcMau' => '' ),
		array( array( 'rowKind' => 'MONEY', 'amount' => 1000, 'price' => 1000, 'soldQty' => 1,
				'hBefore' => 2, 'hAfter' => 1 ),
			array( 'rowKind' => 'MONEY', 'amount' => 1000, 'hBefore' => 1, 'hAfter' => '' ) ) ),
	/* mẫu TÁCH */
	array( array( 'machineType' => 'TIEN', 'bcMau' => 'TACH', 'revMeter' => 500000,
		'revHang' => 450000, 'refundCustomer' => 20000 ),
		array( array( 'rowKind' => 'HANG', 'itemCode' => 'H1', 'soldQty' => 3 ) ) ),
);
$g = goc( 'tien_vs_hang', $ca_tvh, array() );
foreach ( $ca_tvh as $i => $ca ) {
	$gg = isset( $g[ $i ] ) ? $g[ $i ] : null;
	$pp = VHJP_Tinh::tien_vs_hang( $ca[0], $ca[1] );
	if ( null === $gg || null === $pp ) {
		teq( "tien_vs_hang[$i] · cùng ra null hay không", $gg, $pp );
		continue;
	}
	chieu( "tien_vs_hang[$i]", $gg, $pp );
}
$xu_hoan = VHJP_Tinh::tien_vs_hang( $ca_tvh[0][0], $ca_tvh[0][1] );
teq( '🔴 máy XU: KHÔNG trừ hoàn', false, $xu_hoan['truHoan'] );
teq( '🔴 nên tiền đem so đúng bằng tiền đồng hồ, không hụt đi khoản hoàn',
	1000000, $xu_hoan['tienMayRong'] );
teq( 'và kết luận là KHỚP, không đẻ ra lệch 100.000đ', 0, $xu_hoan['lech'] );
$tien_hoan = VHJP_Tinh::tien_vs_hang( $ca_tvh[1][0], $ca_tvh[1][1] );
teq( '🔴 máy TIỀN thì NGƯỢC LẠI: phải TRỪ hoàn', true, $tien_hoan['truHoan'] );
teq( 'nên tiền đem so là 1.000.000 − 100.000', 900000, $tien_hoan['tienMayRong'] );
teq( '🔴 không có dòng hàng ⇒ trả null, đừng in bảng toàn số 0', null,
	VHJP_Tinh::tien_vs_hang( array( 'machineType' => 'TIEN', 'bcMau' => '' ), array() ) );
teq( '🔴 mọi ô chưa nhập đồng hồ đếm trứng ⇒ cũng null (so lại thì lúc nào cũng khớp)', null,
	VHJP_Tinh::tien_vs_hang( array( 'machineType' => 'TIEN', 'bcMau' => '' ),
		array( array( 'rowKind' => 'MONEY', 'amount' => 1000, 'hBefore' => 1, 'hAfter' => '' ) ) ) );
$mot_co = VHJP_Tinh::tien_vs_hang( $ca_tvh[5][0], $ca_tvh[5][1] );
t( '🔴 nhưng CÒN MỘT Ô so được thì VẪN so, và nói ra đã bỏ qua mấy ô',
	is_array( $mot_co ) && 1 === $mot_co['boQua']
		&& false !== mb_strpos( $mot_co['lyDo'], '1/2' ), $mot_co );

// ============================================================ 9. CỔNG
require_once $plg . 'class-vhjp-cong.php';
foreach ( array( 'jpKtListReports', 'jpKtGetReport', 'jpKtApprove', 'jpKtReject' ) as $fn ) {
	t( "cổng khai $fn", isset( VHJP_Cong::map()[ $fn ] ) );
	t( "$fn không còn ở bảng chưa làm", ! in_array( $fn, VHJP_Cong::chua_lam(), true ) );
	t( "🔴 $fn GÁC VAI KẾ TOÁN", in_array( $fn, VHJP_Cong::chi_ke_toan(), true ) );
	t( "$fn KHÔNG gác vai nhân viên", ! in_array( $fn, VHJP_Cong::chi_nhan_vien(), true ) );
}

// ============================================================ kết
if ( $truot ) {
	echo "HỎNG: " . count( $truot ) . "\n";
	foreach ( $truot as $x ) { echo "  ✗ $x\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — đường duyệt của kế toán khớp mã gốc chạy thật.\n";
