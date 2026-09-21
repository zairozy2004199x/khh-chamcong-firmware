<?php
/**
 * ĐỌC BÁO CÁO JP — `VHJP_BaoCao`
 * =============================================================================================
 *
 * 🔴 BÀI NÀY KHÔNG SO PHP VỚI ĐÁP ÁN CHÉP TAY. Nó nạp cùng MỘT bộ dữ liệu vào hai nơi —
 *    cơ sở dữ liệu của bản PHP, và bộ sổ giả của mã gốc chạy bằng node — rồi chạy CHÍNH
 *    `jpGetReport` · `jpMyReports` · `jpPrevClosing_` … của `JP2_05_BaoCao.gs` và đòi hai bên
 *    ra y hệt nhau, từng trường một.
 *
 *    Cầu nối chỉ giả ĐÚNG HAI THỨ: `jpVals_` (mảng thô của Sheets) và `jpAuth_` (người đăng
 *    nhập). Mọi thứ phía trên — `jpRows_` · `jpFind_` · `jpFindOne_` · `jpNeedLoc_` · `jpIsKT_`
 *    — vẫn là mã gốc thật. Xem đầu `jp-doc-goc.js` để biết vì sao seam đặt đúng chỗ ấy.
 *
 * ---------------------------------------------------------------------------------------------
 * 🔴 BA CHỖ BÀI NÀY NHẮM THẲNG VÀO
 * ---------------------------------------------------------------------------------------------
 *  ①  Ô TRỐNG PHẢI CÒN TRỐNG. `mAfter` · `amount` · `cashReal` · `stockActual` để `NULL` trong
 *     sổ phải ra `""`, KHÔNG ra `0`. Ra `0` thì mở lại báo cáo là trạng thái "chưa nhập" biến
 *     mất, danh sách kiểm báo đủ điều kiện nộp, và TỔNG PHẢI NỘP về 0 mà sổ vẫn cân.
 *
 *  ②  `prevClosing` RỖNG PHẢI LÀ OBJECT, KHÔNG PHẢI MẢNG. PHP `array()` ra `[]`, giao diện
 *     tra `prev[r.machineId]` được `undefined` ở mọi dòng ⇒ không dòng nào khoá ô tồn đầu.
 *
 *  ③  CÓ KỲ MỚI HƠN CHƯA DUYỆT thì `ton_ky_truoc()` phải trả RỖNG. Đây là chỗ dễ "dọn" nhất
 *     và dọn là sai: cờ `carried` là cờ KHOÁ Ô, khoá một con số đã cũ là ép nhân viên giữ nó.
 *
 * Chạy: php tools/test/kiem-jp-bao-cao.php
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
foreach ( array( 'db', 'doc', 'nguon', 'ma', 'nhat-ky', 'auth', 'cau-hinh', 'tinh', 'anh', 'bao-cao' ) as $f ) {
	require_once $plg . 'class-vhjp-' . $f . '.php';
}
global $wpdb;
vhcp_stub_dung_bang( VHJP_DB::bang(), $wpdb->prefix . 'vhjp_' );
t( '🔴 dựng được đủ bảng (trượt DDL là mọi phép dưới đây vô nghĩa)',
	! vhcp_stub_loi_ddl(), vhcp_stub_loi_ddl() );

$cau_noi = __DIR__ . '/jp-doc-goc.js';
exec( 'node --version 2>/dev/null', $r_, $ma_node );
t( '🔴 có node để chạy mã gốc (thiếu là KHÔNG đối chiếu được)', 0 === $ma_node );

/* ============================================================ 1. MỘT BỘ DỮ LIỆU, HAI NƠI NẠP
 *
 * Dựng cảnh thật của một cơ sở: ba kỳ nối nhau, hai nhân viên, một kỳ chồng, và một bảng dòng
 * có đủ sáu loại. `null` nghĩa là Ô CHƯA AI GÕ — đúng thứ phép ① ở trên đi tìm.
 */
$SO = array(
	'JP_Users' => array(
		array( 'id' => 'U1', 'hoTen' => 'Nam', 'role' => 'NHANVIEN', 'machineType' => '',
			'locationIds' => 'L1', 'active' => 'Y' ),
		array( 'id' => 'U2', 'hoTen' => 'Bình', 'role' => 'NHANVIEN', 'machineType' => '',
			'locationIds' => 'L1', 'active' => 'Y' ),
		array( 'id' => 'U9', 'hoTen' => 'Kế toán', 'role' => 'KETOAN', 'machineType' => '',
			'locationIds' => '', 'active' => 'Y' ),
	),
	'JP_Locations' => array(
		array( 'id' => 'L1', 'code' => 'AMBD', 'name' => 'AEON Bình Dương', 'maKH' => 'KH00119',
			'machineType' => 'TIEN', 'bcMau' => '', 'coDhTrung' => 'N', 'chonGiaXung' => 'Y',
			'active' => 'Y' ),
		array( 'id' => 'L2', 'code' => 'SBPQ', 'name' => 'Sân bay Phú Quốc', 'maKH' => 'KH00129',
			'machineType' => 'TIEN', 'bcMau' => 'TACH', 'coDhTrung' => '', 'chonGiaXung' => '',
			'active' => 'Y' ),
	),
	'JP_Reports' => array(
		/* ① Kỳ cũ ĐÃ DUYỆT — nguồn tồn cuối kỳ. */
		array( 'id' => 'RP20260801-0001', 'locationId' => 'L1', 'locationName' => 'AEON Bình Dương',
			'maKH' => 'KH00119', 'machineType' => 'TIEN', 'bcMau' => '',
			'fromDate' => '2026-08-01', 'toDate' => '2026-08-15', 'userId' => 'U1',
			'userName' => 'Nam', 'status' => 'HOAN_TAT', 'revMeter' => 5000000,
			'revBank' => 1000000, 'revCashMeter' => 4000000, 'revHang' => 4650000,
			'lechTienHang' => 350000, 'adjMachine' => 0, 'adjMachineNote' => '',
			'refundCustomer' => 200000, 'refundNote' => 'khách trả', 'refundRows' => 100000,
			'cashActual' => 3900000, 'totalSubmit' => 4700000,
			'submittedAt' => '2026-08-16 08:30:00', 'apprBy' => 'ketoan',
			'apprAt' => '2026-08-17 09:00:00', 'payStatus' => 'DA_NOP', 'paid' => 4700000,
			'paidDate' => '2026-08-18', 'warnCount' => 2, 'remark' => 'ổn' ),
		/* ② Kỳ giữa ĐÃ NỘP nhưng CHƯA DUYỆT — chính nó làm ③ ở đầu tệp có hiệu lực. */
		array( 'id' => 'RP20260816-0002', 'locationId' => 'L1', 'locationName' => 'AEON Bình Dương',
			'maKH' => 'KH00119', 'machineType' => 'TIEN', 'bcMau' => '',
			'fromDate' => '2026-08-16', 'toDate' => '2026-08-31', 'userId' => 'U1',
			'userName' => 'Nam', 'status' => 'CHO_DUYET', 'revMeter' => 3000000,
			'totalSubmit' => 2900000, 'warnCount' => 0,
			'submittedAt' => '2026-09-01 07:10:00', 'apprRevBy' => 'ketoan',
			'apprRevAt' => '2026-09-02 10:00:00' ),
		/* ③ Kỳ đang làm. */
		array( 'id' => 'RP20260901-0003', 'locationId' => 'L1', 'locationName' => 'AEON Bình Dương',
			'maKH' => 'KH00119', 'machineType' => 'TIEN', 'bcMau' => '',
			'fromDate' => '2026-09-01', 'toDate' => '2026-09-15', 'userId' => 'U1',
			'userName' => 'Nam', 'status' => 'NHAP', 'revMeter' => 0, 'warnCount' => 0 ),
		/* ④ Cùng kỳ, cùng cơ sở, NGƯỜI KHÁC — `trung_nguoi_khac` phải thấy. */
		array( 'id' => 'RP20260901-0004', 'locationId' => 'L1', 'locationName' => 'AEON Bình Dương',
			'machineType' => 'TIEN', 'fromDate' => '2026-09-01', 'toDate' => '2026-09-15',
			'userId' => 'U2', 'userName' => 'Bình', 'status' => 'NHAP' ),
		/* ⑤ ĐÃ NỘP và KỲ CHỒNG lên ③ — `ky_chong_nhau` phải thấy, ④ (nháp) thì không. */
		array( 'id' => 'RP20260905-0005', 'locationId' => 'L1', 'locationName' => 'AEON Bình Dương',
			'machineType' => 'TIEN', 'fromDate' => '2026-09-05', 'toDate' => '2026-09-20',
			'userId' => 'U2', 'userName' => 'Bình', 'status' => 'CHO_DUYET' ),
		/* ⑥ Cùng cơ sở, cùng kỳ, nhưng MÁY XU — không được lẫn vào đâu cả. */
		array( 'id' => 'RP20260901-0006', 'locationId' => 'L1', 'locationName' => 'AEON Bình Dương',
			'machineType' => 'XU', 'fromDate' => '2026-09-01', 'toDate' => '2026-09-15',
			'userId' => 'U2', 'userName' => 'Bình', 'status' => 'HOAN_TAT' ),
	),
	'JP_Zones' => array(
		array( 'id' => 'Z2', 'reportId' => 'RP20260901-0003', 'seq' => 2, 'name' => 'Tầng 2',
			'clusterId' => 'C2', 'note' => 'gần thang' ),
		array( 'id' => 'Z1', 'reportId' => 'RP20260901-0003', 'seq' => 1, 'name' => 'Tầng 1',
			'clusterId' => 'C1', 'note' => '' ),
	),
	'JP_Rows' => array(
		/* --- dòng của kỳ ĐÃ DUYỆT: đây là nguồn tồn cuối --- */
		array( 'id' => 'R901', 'reportId' => 'RP20260801-0001', 'zoneId' => 'Z9', 'seq' => 1,
			'rowKind' => 'MONEY', 'machineId' => 'M1', 'machineCode' => '100JP111',
			'itemCode' => '100JP111', 'itemMisa' => 'MS111', 'price' => 20000,
			'mBefore' => 1240, 'mAfter' => 1300, 'hBefore' => 48, 'hAfter' => 51,
			'stockActual' => 51, 'stockLeftCalc' => 52 ),
		array( 'id' => 'R902', 'reportId' => 'RP20260801-0001', 'zoneId' => 'Z9', 'seq' => 2,
			'rowKind' => 'HANG', 'machineId' => '', 'itemCode' => 'H01', 'itemMisa' => 'MSH01',
			'price' => 15000, 'stockActual' => null, 'stockLeftCalc' => 33 ),
		/* --- dòng của kỳ ĐANG LÀM: đủ loại, và ĐỦ Ô TRỐNG --- */
		array( 'id' => 'R2', 'reportId' => 'RP20260901-0003', 'zoneId' => 'Z1', 'seq' => 2,
			'rowKind' => 'MONEY', 'machineId' => 'M2', 'machineCode' => '150JP113',
			'itemCode' => '150JP113', 'itemMisa' => 'MS113', 'price' => null,
			'mBefore' => 730, 'mAfter' => null, 'amount' => null, 'cash' => null,
			'cashReal' => null, 'stockActual' => null, 'giaXung' => null,
			'warnJson' => '', 'xuDaysJson' => '' ),
		array( 'id' => 'R1', 'reportId' => 'RP20260901-0003', 'zoneId' => 'Z1', 'seq' => 1,
			'rowKind' => 'MONEY', 'machineId' => 'M1', 'machineCode' => '100JP111',
			'itemCode' => '100JP111', 'itemMisa' => 'MS111', 'itemName' => 'Trứng 20k',
			'price' => 20000, 'mBefore' => 1300, 'mAfter' => 1360, 'mActual' => 60,
			'collection' => 30, 'amount' => 1200000, 'cash' => 900000, 'bank' => 300000,
			'cashReal' => 880000, 'hOpen' => 51, 'hBefore' => 51, 'hAfter' => 54,
			'soldQty' => 3, 'hLeft' => 48, 'giaXung' => 10000, 'refundAmt' => 40000,
			'refundQty' => 2, 'refundRowNote' => 'trả khách',
			'warnJson' => '[{"code":"W9","part":"REV","msg":"x","detail":"y"}]',
			'xuDaysJson' => '', 'note' => 'ok' ),
		array( 'id' => 'R3', 'reportId' => 'RP20260901-0003', 'zoneId' => 'Z2', 'seq' => 3,
			'rowKind' => 'HANG', 'machineId' => '', 'itemCode' => 'H01', 'itemMisa' => 'MSH01',
			'itemName' => 'Gấu bông', 'price' => 15000, 'stockOpen' => 33, 'addQty1' => 10,
			'stockLeftCalc' => 40, 'stockActual' => null, 'defectQty' => 1, 'returnQty' => 2,
			'warnJson' => 'KHÔNG PHẢI JSON', 'xuDaysJson' => '[1,2,3]' ),
		array( 'id' => 'R4', 'reportId' => 'RP20260901-0003', 'zoneId' => 'Z2', 'seq' => 4,
			'rowKind' => 'NGOAI', 'machineId' => '', 'itemCode' => 'H02', 'itemMisa' => 'MSH02',
			'price' => 30000, 'stockOpen' => 5, 'stockActual' => 4,
			'warnJson' => '', 'xuDaysJson' => '' ),
		array( 'id' => 'R5', 'reportId' => 'RP20260901-0003', 'zoneId' => 'Z2', 'seq' => 5,
			'rowKind' => 'COIN', 'machineId' => 'M5', 'machineCode' => 'XU01',
			'cBefore' => 100, 'cAfter' => 140, 'cActual' => 40, 'giaXu' => 50000,
			'xuTong' => 2000000, 'xuLa' => 0, 'mBefore' => 10, 'mAfter' => 14,
			'warnJson' => '', 'xuDaysJson' => '' ),
		/* Dòng của báo cáo KHÁC — không được lọt vào lượt mở ③. */
		array( 'id' => 'R99', 'reportId' => 'RP20260901-0004', 'zoneId' => 'Z9', 'seq' => 1,
			'rowKind' => 'MONEY', 'machineId' => 'M1', 'mBefore' => 1, 'mAfter' => 2 ),
	),
	'JP_Photos' => array(
		array( 'id' => 'P1', 'reportId' => 'RP20260901-0003', 'scope' => 'ROW', 'refId' => 'R1',
			'kind' => 'METER_AFTER', 'fileId' => 'FILE abc/1', 'url' => 'https://cu/x',
			'takenAt' => '2026-09-15 18:20', 'bytes' => 1234 ),
		/* Bản ghi ĐỜI ĐẦU: không có `fileId` ⇒ phải rơi về cột `url` đã lưu, không mất ảnh. */
		array( 'id' => 'P2', 'reportId' => 'RP20260901-0003', 'scope' => 'ZONE', 'refId' => 'Z1',
			'kind' => 'PAYBOX', 'fileId' => '', 'url' => 'https://cu/paybox',
			'takenAt' => '', 'bytes' => 0 ),
	),
);

/* Nạp vào cơ sở dữ liệu của bản PHP. */
foreach ( $SO as $tab => $ds ) {
	foreach ( $ds as $hang ) {
		$ok = VHJP_Nguon::them( $tab, $hang );
		t( "nạp được $tab#" . $hang['id'], false !== $ok, VHJP_Nguon::loi_cuoi() );
	}
}
teq( 'sổ báo cáo đúng 6 dòng', 6, count( VHJP_Nguon::doc( 'JP_Reports' ) ) );
teq( 'sổ dòng đúng 8 dòng', 8, count( VHJP_Nguon::doc( 'JP_Rows' ) ) );

$NV  = array( 'id' => 'U1', 'hoTen' => 'Nam', 'role' => 'NHANVIEN', 'machineType' => '',
	'locationIds' => array( 'L1' ) );
$NV2 = array( 'id' => 'U2', 'hoTen' => 'Bình', 'role' => 'NHANVIEN', 'machineType' => '',
	'locationIds' => array( 'L1' ) );
$KT  = array( 'id' => 'U9', 'hoTen' => 'Kế toán', 'role' => 'KETOAN', 'machineType' => '',
	'locationIds' => array() );

// ============================================================ 2. Cầu nối
function goc( $ten, $ca, $so = null, $ai = null ) {
	global $cau_noi, $SO;
	$ra = array(); $ma = 0;
	$lenh = 'node ' . escapeshellarg( $cau_noi ) . ' ' . escapeshellarg( $ten ) . ' '
		. escapeshellarg( wp_json_encode( $ca ) );
	if ( false !== $so ) { $lenh .= ' ' . escapeshellarg( wp_json_encode( null === $so ? $SO : $so ) ); }
	if ( null !== $ai ) { $lenh .= ' ' . escapeshellarg( wp_json_encode( $ai ) ); }
	exec( $lenh . ' 2>&1', $ra, $ma );
	if ( 0 !== $ma ) { return array( 'loi' => implode( "\n", $ra ) ); }
	return json_decode( implode( '', $ra ), true );
}

/**
 * Đối chiếu MỘT lượt gọi, TỪNG TRƯỜNG.
 *
 * ⚠️ So từng trường chứ không so cả object bằng một phép: so cả object thì câu báo trượt chỉ
 *    nói "khác nhau", còn người đọc phải tự dò trong hai khối JSON 40 trường xem lệch ở đâu.
 */
function chieu( $nhan, $goc_ra, $php_ra, $bo_qua = array() ) {
	if ( isset( $goc_ra['loi'] ) ) {
		t( "🔴 chạy được mã gốc cho $nhan", false, $goc_ra['loi'] );
		return;
	}
	if ( ! is_array( $goc_ra ) || ! is_array( $php_ra ) ) {
		t( "$nhan · hai bên cùng kiểu", wp_json_encode( $goc_ra ) === wp_json_encode( $php_ra ),
			'gốc ' . wp_json_encode( $goc_ra ) . ' · PHP ' . wp_json_encode( $php_ra ) );
		return;
	}
	$khoa = array_unique( array_merge( array_keys( $goc_ra ), array_keys( $php_ra ) ) );
	foreach ( $khoa as $k ) {
		if ( in_array( $k, $bo_qua, true ) ) { continue; }
		$g = array_key_exists( $k, $goc_ra ) ? $goc_ra[ $k ] : '«THIẾU»';
		$p = array_key_exists( $k, $php_ra ) ? $php_ra[ $k ] : '«THIẾU»';
		t( "$nhan · $k", wp_json_encode( $g ) === wp_json_encode( $p ),
			'gốc ' . wp_json_encode( $g ) . ' · PHP ' . wp_json_encode( $p ) );
	}
}

// ============================================================ 3. ĐÓNG GÓI ĐẦU BÁO CÁO
$bc1 = VHJP_Nguon::tim_mot( 'JP_Reports', 'id', 'RP20260801-0001' );
$bc3 = VHJP_Nguon::tim_mot( 'JP_Reports', 'id', 'RP20260901-0003' );
t( '🔴 đọc lại được đầu báo cáo từ sổ', is_array( $bc1 ) && is_array( $bc3 ) );

$g = goc( 'pub_head', array( array( $SO['JP_Reports'][0] ) ) );
chieu( 'pub_head · kỳ đã duyệt', isset( $g[0] ) ? $g[0] : $g, VHJP_BaoCao::pub_head( $bc1 ) );
$g = goc( 'pub_head', array( array( $SO['JP_Reports'][2] ) ) );
chieu( 'pub_head · kỳ đang làm', isset( $g[0] ) ? $g[0] : $g, VHJP_BaoCao::pub_head( $bc3 ) );

/* Nói thẳng vài chốt, để người đọc thấy mà không phải chạy node. */
$ph = VHJP_BaoCao::pub_head( $bc1 );
teq( '🔴 refundTotal = hoàn khách + hoàn theo dòng', 300000, $ph['refundTotal'] );
teq( '🔴 revMeterRong = doanh thu đồng hồ TRỪ hoàn', 4700000, $ph['revMeterRong'] );
t( '🔴 pub_head KHÔNG rò `userId` ra giao diện', ! array_key_exists( 'userId', $ph ), array_keys( $ph ) );

// ============================================================ 4. CHỮ KÝ — ba đời dữ liệu
$g = goc( 'chu_ky', array(
	array( $SO['JP_Reports'][0] ),      // ký GỘP (apprBy)
	array( $SO['JP_Reports'][1] ),      // mới ký phần doanh thu
	array( $SO['JP_Reports'][2] ),      // chưa ký
	array( null ),
), false );
foreach ( array( 0 => $SO['JP_Reports'][0], 1 => $SO['JP_Reports'][1], 2 => $SO['JP_Reports'][2],
	3 => null ) as $i => $h ) {
	chieu( "chu_ky[$i]", isset( $g[ $i ] ) ? $g[ $i ] : null, VHJP_BaoCao::chu_ky( $h ) );
}
$ck = VHJP_BaoCao::chu_ky( $SO['JP_Reports'][0] );
t( '🔴 chữ ký GỘP đời cũ được kể là ký CẢ HAI phần', true === $ck['duCaHai'], $ck );
$ck2 = VHJP_BaoCao::chu_ky( $SO['JP_Reports'][1] );
t( '🔴 mới ký doanh thu thì CHƯA đủ hai chữ ký', false === $ck2['duCaHai'], $ck2 );

// ============================================================ 5. ĐÓNG GÓI KHU VỰC & DÒNG
$khu = VHJP_Nguon::tim( 'JP_Zones', 'reportId', 'RP20260901-0003' );
foreach ( $khu as $z ) {
	$g = goc( 'pub_khu', array( array( $z ) ), false );
	chieu( 'pub_khu#' . $z['id'], isset( $g[0] ) ? $g[0] : $g, VHJP_BaoCao::pub_khu( $z ) );
}

/* `prev` dựng từ chính mã gốc, để hai bên cùng một bảng tra. */
$prev_php = VHJP_BaoCao::ton_ky_truoc( 'L1', '2026-09-01', 'RP20260901-0003', 'TIEN' );
$g = goc( 'ton_ky_truoc', array( array( 'L1', '2026-09-01', 'RP20260901-0003', 'TIEN' ) ) );
$prev_goc = isset( $g[0] ) ? $g[0] : array();
chieu( 'ton_ky_truoc · cả bảng', $prev_goc, $prev_php );

$dong = VHJP_Nguon::tim( 'JP_Rows', 'reportId', 'RP20260901-0003' );
foreach ( $dong as $r ) {
	$g = goc( 'pub_dong', array( array( $r, $prev_goc ) ), false );
	chieu( 'pub_dong#' . $r['id'], isset( $g[0] ) ? $g[0] : $g,
		VHJP_BaoCao::pub_dong( $r, $prev_php ) );
}

/* 🔴 PHÉP ① — ô trống phải CÒN trống. Nói riêng ra vì đây là chỗ mất tiền im lặng. */
$r2 = VHJP_Nguon::tim_mot( 'JP_Rows', 'id', 'R2' );
$p2 = VHJP_BaoCao::pub_dong( $r2, $prev_php );
foreach ( array( 'mAfter', 'amount', 'cash', 'cashReal', 'stockActual', 'hAfter', 'cAfter' ) as $c ) {
	teq( "🔴 ô chưa ai gõ ($c) ra chuỗi RỖNG chứ không ra 0", '', $p2[ $c ] );
}
$r1 = VHJP_Nguon::tim_mot( 'JP_Rows', 'id', 'R1' );
$p1 = VHJP_BaoCao::pub_dong( $r1, $prev_php );
teq( 'ô đã gõ thì ra số', 1360, $p1['mAfter'] );
teq( '🔴 giaXung của CHÍNH dòng phải trả về, không để rơi về mặc định', 10000, $p1['giaXung'] );
teq( '🔴 dòng chưa chọn giá xung thì rơi về mặc định', 5000, $p2['giaXung'] );
teq( 'warnJson hỏng thì ra mảng rỗng, KHÔNG làm gãy cả báo cáo',
	array(), VHJP_BaoCao::pub_dong( VHJP_Nguon::tim_mot( 'JP_Rows', 'id', 'R3' ), $prev_php )['warns'] );
teq( 'warnJson đọc được thì giữ nguyên mã', 'W9', $p1['warns'][0]['code'] );

/* 🔴 Ở ĐÂY bảng tra CỐ Ý rỗng (xem §7), nên KHÔNG dòng nào được khoá — và đó chính là điều
   phải đòi: `carried` phải đi theo bảng tra, đừng tự suy từ chỗ khác. */
teq( '🔴 bảng tra rỗng ⇒ không dòng nào bị khoá ô', false, $p1['carried'] );

// ============================================================ 6. ẢNH
foreach ( VHJP_Nguon::tim( 'JP_Photos', 'reportId', 'RP20260901-0003' ) as $p ) {
	$g = goc( 'pub_anh', array( array( $p ) ), false );
	chieu( 'pub_anh#' . $p['id'], isset( $g[0] ) ? $g[0] : $g, VHJP_BaoCao::pub_anh( $p ) );
}
$g = goc( 'anh_url', array( array( 'FILE abc/1', 'w200' ), array( '', 'w200' ),
	array( 'x', '' ), array( null, null ) ), false );
foreach ( array( array( 'FILE abc/1', 'w200' ), array( '', 'w200' ), array( 'x', '' ),
	array( null, null ) ) as $i => $ca ) {
	teq( 'anh_url(' . json_encode( $ca ) . ')', isset( $g[ $i ] ) ? $g[ $i ] : null,
		VHJP_BaoCao::anh_url( $ca[0], null === $ca[1] ? '' : $ca[1] ) );
}

// ============================================================ 7. TỒN CUỐI KỲ TRƯỚC
/* 🔴 PHÉP ③ — kỳ ② đang CHO_DUYET nằm giữa, nên KHÔNG chốt gì cả. */
teq( '🔴 có kỳ mới hơn CHƯA DUYỆT ⇒ không khoá ô nào', array(),
	VHJP_BaoCao::ton_ky_truoc( 'L1', '2026-09-01', 'RP20260901-0003', 'TIEN' ) );

/* Bỏ kỳ ② đi thì mới chốt được theo kỳ ①. */
VHJP_Nguon::xoa( 'JP_Reports', 'RP20260816-0002' );
$SO2 = $SO;
$SO2['JP_Reports'] = array_values( array_filter( $SO['JP_Reports'],
	function ( $r ) { return 'RP20260816-0002' !== $r['id']; } ) );

$prev2_php = VHJP_BaoCao::ton_ky_truoc( 'L1', '2026-09-01', 'RP20260901-0003', 'TIEN' );
$g = goc( 'ton_ky_truoc', array( array( 'L1', '2026-09-01', 'RP20260901-0003', 'TIEN' ) ), $SO2 );
chieu( '🔴 ton_ky_truoc · sau khi bỏ kỳ chưa duyệt', isset( $g[0] ) ? $g[0] : array(), $prev2_php );
t( '🔴 và lúc ấy CÓ chốt được (không rỗng)', count( $prev2_php ) > 0, $prev2_php );
teq( 'khoá dòng máy là machineId', 1300, $prev2_php['M1']['mAfter'] );
teq( 'khoá dòng hàng là ITEM:<mã>', 'RP20260801-0001', $prev2_php['ITEM:H01']['fromReport'] );
teq( '🔴 mang theo stockLeftCalc, không chỉ số ĐẾM (mã không đếm thì kỳ sau mất hàng)',
	33, $prev2_php['ITEM:H01']['stockLeftCalc'] );

/* 🔴 `carried` tra theo ĐÚNG khoá của TỪNG LOẠI DÒNG — giờ mới có bảng tra thật để đòi. */
teq( '🔴 dòng máy nối kỳ theo machineId', true,
	VHJP_BaoCao::pub_dong( VHJP_Nguon::tim_mot( 'JP_Rows', 'id', 'R1' ), $prev2_php )['carried'] );
teq( '🔴 dòng HÀNG nối kỳ theo MÃ HÀNG (machineId rỗng nên tra kiểu kia là trượt)', true,
	VHJP_BaoCao::pub_dong( VHJP_Nguon::tim_mot( 'JP_Rows', 'id', 'R3' ), $prev2_php )['carried'] );
teq( 'mã hàng chưa từng có ở kỳ trước thì KHÔNG khoá ô', false,
	VHJP_BaoCao::pub_dong( VHJP_Nguon::tim_mot( 'JP_Rows', 'id', 'R4' ), $prev2_php )['carried'] );

/* Lọc theo LOẠI MÁY: kỳ máy xu cùng cơ sở không được chảy sang. */
$g = goc( 'ton_ky_truoc', array( array( 'L1', '2026-09-01', '', 'XU' ) ), $SO2 );
chieu( 'ton_ky_truoc · lọc loại máy XU', isset( $g[0] ) ? $g[0] : array(),
	VHJP_BaoCao::ton_ky_truoc( 'L1', '2026-09-01', '', 'XU' ) );

/* Nạp lại kỳ ② cho mấy phép sau. */
VHJP_Nguon::them( 'JP_Reports', $SO['JP_Reports'][1] );

// ============================================================ 8. KỲ CHỒNG & TRÙNG NGƯỜI
$g = goc( 'ky_chong_nhau', array( array( $SO['JP_Reports'][2] ) ) );
$kc_goc = isset( $g[0] ) ? $g[0] : array();
$kc_php = VHJP_BaoCao::ky_chong_nhau( $bc3 );
teq( 'ky_chong_nhau · đúng số lượng', count( $kc_goc ), count( $kc_php ) );
teq( 'ky_chong_nhau · đúng danh sách mã', array_column( $kc_goc, 'id' ),
	array_map( 'strval', array_column( $kc_php, 'id' ) ) );
teq( '🔴 chỉ kể báo cáo ĐÃ NỘP — bản nháp chồng nhau là chuyện thường',
	array( 'RP20260905-0005' ), array_map( 'strval', array_column( $kc_php, 'id' ) ) );

$g = goc( 'trung_nguoi_khac', array( array( $SO['JP_Reports'][2], 'U1' ) ) );
chieu( 'trung_nguoi_khac', array( 'x' => isset( $g[0] ) ? $g[0] : null ),
	array( 'x' => VHJP_BaoCao::trung_nguoi_khac( $bc3, 'U1' ) ) );
teq( '🔴 thấy đúng người kia, không thấy chính mình', array( 'Bình' ),
	array_column( VHJP_BaoCao::trung_nguoi_khac( $bc3, 'U1' ), 'userName' ) );

// ============================================================ 9. MỞ Ô TỒN ĐẦU
$g = goc( 'mo_ton_dau', array( array( $SO['JP_Reports'][2] ), array( $SO['JP_Reports'][0] ),
	array( array( 'id' => 'X', 'locationId' => 'L1', 'fromDate' => '' ) ) ) );
foreach ( array( $bc3, $bc1, array( 'id' => 'X', 'locationId' => 'L1', 'fromDate' => '' ) ) as $i => $h ) {
	teq( "mo_ton_dau[$i]", isset( $g[ $i ] ) ? $g[ $i ] : null, VHJP_BaoCao::mo_ton_dau( $h ) );
}
/*
 * 🔴 MỘT CHỖ KHÔNG ĐỐI XỨNG CỦA BẢN GỐC — GIỮ NGUYÊN, ĐỪNG "DỌN".
 *
 * `ton_ky_truoc()` LỌC theo loại máy (báo cáo xu không được chảy sang báo cáo tiền), còn
 * `mo_ton_dau()` thì KHÔNG: nó chỉ hỏi "tháng này đã có báo cáo HOÀN TẤT nào ở cơ sở này
 * chưa". Nên ở sổ thử này, báo cáo MÁY XU đã duyệt (`RP20260901-0006`) đóng luôn ô tồn đầu
 * của báo cáo MÁY TIỀN cùng tháng. Mã gốc chạy thật ra đúng như vậy — phép đối chiếu ngay
 * trên đã đòi rồi — nên bản này chép y nguyên. Sửa cho "hợp lý" là đổi hành vi của 13 cơ sở
 * đang chạy mà không ai yêu cầu.
 */
teq( '🔴 tháng đã có báo cáo HOÀN TẤT (dù là MÁY XU) ⇒ KHOÁ ô tồn đầu',
	false, VHJP_BaoCao::mo_ton_dau( $bc3 ) );

/* Bỏ báo cáo đã duyệt của tháng 9 đi thì ô mở lại — và bản nháp của chính mình vẫn còn đó,
   tức nháp KHÔNG tự khoá ô của mình. */
VHJP_Nguon::xoa( 'JP_Reports', 'RP20260901-0006' );
$SO3 = $SO;
$SO3['JP_Reports'] = array_values( array_filter( $SO['JP_Reports'],
	function ( $r ) { return 'RP20260901-0006' !== $r['id']; } ) );
$g = goc( 'mo_ton_dau', array( array( $SO['JP_Reports'][2] ) ), $SO3 );
teq( '🔴 tháng chưa có báo cáo HOÀN TẤT nào ⇒ MỞ ô tồn đầu (khớp mã gốc)',
	isset( $g[0] ) ? $g[0] : null, VHJP_BaoCao::mo_ton_dau( $bc3 ) );
teq( '🔴 và bản nháp của CHÍNH MÌNH không tự khoá ô của mình',
	true, VHJP_BaoCao::mo_ton_dau( $bc3 ) );
VHJP_Nguon::them( 'JP_Reports', $SO['JP_Reports'][5] );

// ============================================================ 10. QUYỀN TRÊN MỘT BÁO CÁO
/*
 * 🔴 PHẢI ĐI QUA CẢ BỐN TRẠNG THÁI, không chỉ NHÁP và HOÀN TẤT.
 *
 * `CHO_DUYET` là báo cáo ĐANG NẰM TRÊN BÀN KẾ TOÁN. Cho chính chủ sửa tiếp là viết lại một
 * bản đã nộp, trong khi kế toán đang soát đúng bản ấy — số đổi dưới tay người ta mà không
 * dòng nào báo. `CAN_SUA` thì ngược lại: kế toán đã trả về, PHẢI sửa được, không thì nhân
 * viên kẹt cứng. Thiếu hai ca này là bảng kiểm không phân biệt nổi hai chiều ngược nhau.
 */
$bc2 = VHJP_Nguon::tim_mot( 'JP_Reports', 'id', 'RP20260816-0002' );   // CHO_DUYET, của U1
$bc_sua = $SO['JP_Reports'][1];
$bc_sua['id'] = 'RP20260816-0009';
$bc_sua['status'] = 'CAN_SUA';
VHJP_Nguon::them( 'JP_Reports', $bc_sua );
$bc9 = VHJP_Nguon::tim_mot( 'JP_Reports', 'id', 'RP20260816-0009' );

$ca_sua = array(
	array( $NV,  $bc3, $SO['JP_Reports'][2] ),
	array( $NV2, $bc3, $SO['JP_Reports'][2] ),
	array( $KT,  $bc3, $SO['JP_Reports'][2] ),
	array( $NV,  $bc1, $SO['JP_Reports'][0] ),
	array( $NV,  $bc2, $SO['JP_Reports'][1] ),
	array( $NV,  $bc9, $bc_sua ),
);
$g = goc( 'sua_duoc', array_map( function ( $c ) { return array( $c[0], $c[2] ); }, $ca_sua ), false );
foreach ( $ca_sua as $i => $ca ) {
	teq( "sua_duoc[$i]", isset( $g[ $i ] ) ? $g[ $i ] : null,
		VHJP_BaoCao::sua_duoc( $ca[0], $ca[1] ) );
}
teq( '🔴 ĐÃ NỘP (CHO_DUYET) thì chính chủ cũng KHÔNG sửa — bản ấy đang trên bàn kế toán',
	false, VHJP_BaoCao::sua_duoc( $NV, $bc2 ) );
teq( '🔴 kế toán TRẢ VỀ (CAN_SUA) thì chính chủ PHẢI sửa được',
	true, VHJP_BaoCao::sua_duoc( $NV, $bc9 ) );
VHJP_Nguon::xoa( 'JP_Reports', 'RP20260816-0009' );
teq( '🔴 chủ báo cáo + còn NHÁP ⇒ sửa được', true, VHJP_BaoCao::sua_duoc( $NV, $bc3 ) );
teq( '🔴 người khác cùng cơ sở ⇒ KHÔNG sửa được', false, VHJP_BaoCao::sua_duoc( $NV2, $bc3 ) );
teq( '🔴 kế toán cũng KHÔNG sửa ở màn nhân viên', false, VHJP_BaoCao::sua_duoc( $KT, $bc3 ) );
teq( '🔴 đã HOÀN TẤT thì chính chủ cũng thôi', false, VHJP_BaoCao::sua_duoc( $NV, $bc1 ) );

$nem = '';
try { VHJP_BaoCao::can_chu( $NV2, $bc3 ); } catch ( Throwable $e ) { $nem = $e->getMessage(); }
t( '🔴 can_chu NÉM khi không phải chủ (chặn ở máy chủ, không chỉ ẩn nút)',
	false !== mb_strpos( $nem, 'Nam' ), $nem );
$nem = 'KHÔNG NÉM';
try { VHJP_BaoCao::can_chu( $NV, $bc3 ); $nem = ''; } catch ( Throwable $e ) { $nem = $e->getMessage(); }
teq( 'can_chu im lặng khi đúng chủ', '', $nem );

// ============================================================ 11. DANH SÁCH BÁO CÁO CỦA TÔI
$g = goc( 'cua_toi', array( array( 'the', 0 ) ), null, $NV );
$ds_goc = isset( $g[0] ) ? $g[0] : array();
$ds_php = VHJP_BaoCao::cua_toi( $NV, 0 );
teq( 'cua_toi · đúng số lượng', count( $ds_goc ), count( $ds_php ) );
foreach ( $ds_goc as $i => $x ) {
	chieu( "cua_toi[$i]", $x, isset( $ds_php[ $i ] ) ? $ds_php[ $i ] : array() );
}
teq( '🔴 chỉ báo cáo của CHÍNH mình', array( 'U1' ),
	array_values( array_unique( array_map( function ( $r ) { return $r['userId']; },
		VHJP_Nguon::tim( 'JP_Reports', 'userId', 'U1' ) ) ) ) );
teq( '🔴 xếp MỚI NHẤT TRƯỚC', 'RP20260901-0003', $ds_php[0]['id'] );
teq( 'giới hạn mặc định là 30', 30, VHJP_BaoCao::GIOI_HAN_MAC_DINH );
teq( 'giới hạn truyền vào có hiệu lực', 1, count( VHJP_BaoCao::cua_toi( $NV, 1 ) ) );

// ============================================================ 12. 🔴 MỞ MỘT BÁO CÁO
/*
 * `anhTienDo` là trường DUY NHẤT hai bên khác nhau, và khác CÓ CHỦ Ý: đường ảnh chưa chuyển.
 * Bỏ qua nó ở phép đối chiếu, rồi kiểm riêng rằng PHP trả đúng `null` — tức là đi đúng nhánh
 * lùi mà bản gốc đã dựng sẵn, chứ không phải trả bừa một object rỗng.
 */
$g = goc( 'lay_bao_cao', array( array( 'the', 'RP20260901-0003' ) ), null, $NV );
$lay_goc = isset( $g[0] ) ? $g[0] : $g;
$lay_php = VHJP_BaoCao::lay( $NV, 'RP20260901-0003' );

t( '🔴 mã gốc mở được báo cáo', is_array( $lay_goc ) && ! isset( $lay_goc['loi'] ),
	is_array( $lay_goc ) ? array_keys( $lay_goc ) : $lay_goc );
if ( is_array( $lay_goc ) && ! isset( $lay_goc['loi'] ) ) {
	foreach ( array( 'ok', 'coDhTrung', 'chonGiaXung', 'canEdit' ) as $k ) {
		teq( "lay · $k", isset( $lay_goc[ $k ] ) ? $lay_goc[ $k ] : null, $lay_php[ $k ] );
	}
	chieu( 'lay · head', $lay_goc['head'], $lay_php['head'] );
	teq( 'lay · số khu vực', count( $lay_goc['zones'] ), count( $lay_php['zones'] ) );
	foreach ( $lay_goc['zones'] as $i => $z ) { chieu( "lay · zones[$i]", $z, $lay_php['zones'][ $i ] ); }
	teq( 'lay · số dòng', count( $lay_goc['rows'] ), count( $lay_php['rows'] ) );
	foreach ( $lay_goc['rows'] as $i => $r ) { chieu( "lay · rows[$i]", $r, $lay_php['rows'][ $i ] ); }
	teq( 'lay · số ảnh', count( $lay_goc['photos'] ), count( $lay_php['photos'] ) );
	foreach ( $lay_goc['photos'] as $i => $p ) { chieu( "lay · photos[$i]", $p, $lay_php['photos'][ $i ] ); }
	teq( 'lay · số cảnh báo đầu báo cáo', count( $lay_goc['headWarns'] ), count( $lay_php['headWarns'] ) );
	foreach ( $lay_goc['headWarns'] as $i => $w ) {
		chieu( "lay · headWarns[$i]", $w, $lay_php['headWarns'][ $i ] );
	}
	teq( 'lay · trungNguoiKhac', $lay_goc['trungNguoiKhac'], $lay_php['trungNguoiKhac'] );
	/* ⚠️ So trên MẢNG: `json_decode(..., true)` biến `{}` của mã gốc thành `array()`, nên so
	   bằng `wp_json_encode` hai bên là so `[]` với `{}` — một phép trượt GIẢ, và nó che mất
	   phép thật ngay bên dưới. Hình dạng JSON được đòi riêng ở đó. */
	teq( 'lay · prevClosing', $lay_goc['prevClosing'], (array) $lay_php['prevClosing'] );
}

/* 🔴 PHÉP ② — bảng tra RỖNG phải ra `{}`, không ra `[]`. */
t( '🔴 prevClosing rỗng vẫn là OBJECT trong JSON',
	false !== strpos( wp_json_encode( $lay_php ), '"prevClosing":{}' ),
	substr( wp_json_encode( $lay_php ), 0, 200 ) );
/* 🔴 TIẾN ĐỘ ẢNH đi cùng lượt mở báo cáo — bản gốc cố ý gộp vào đây, và nay bản này cũng vậy.
   So với mã gốc chạy thật, không chỉ so "có khác null không". */
if ( is_array( $lay_goc ) && isset( $lay_goc['anhTienDo'] ) ) {
	chieu( 'lay · anhTienDo', $lay_goc['anhTienDo'], (array) $lay_php['anhTienDo'],
		array( 'warns' ) );
	$wg = array_column( $lay_goc['anhTienDo']['warns'], 'detail' );
	$wp = array_column( $lay_php['anhTienDo']['warns'], 'detail' );
	teq( 'lay · anhTienDo · đúng bộ câu cảnh báo thiếu ảnh', $wg, $wp );
} else {
	t( '🔴 mã gốc cũng trả tiến độ ảnh', false,
		is_array( $lay_goc ) ? array_keys( $lay_goc ) : $lay_goc );
}
t( '🔴 và nó đếm được chỗ thiếu ảnh, không phải null',
	is_array( $lay_php['anhTienDo'] ) && $lay_php['anhTienDo']['missing'] > 0,
	$lay_php['anhTienDo'] );

/* Cảnh báo KỲ CHỒNG phải có mặt, và mang ĐÚNG mã W11 (không phải `?`). */
$ma_w = array_column( $lay_php['headWarns'], 'code' );
t( '🔴 mở báo cáo có kỳ chồng ⇒ cảnh báo W11', in_array( 'W11', $ma_w, true ), $ma_w );
$w11 = '';
foreach ( $lay_php['headWarns'] as $w ) { if ( 'W11' === $w['code'] ) { $w11 = $w['detail']; } }
t( 'và câu cảnh báo NÊU ĐÍCH DANH báo cáo chồng',
	false !== strpos( $w11, 'RP20260905-0005' ), $w11 );

/* Không lẫn dòng của báo cáo khác. */
teq( '🔴 chỉ lấy dòng của ĐÚNG báo cáo ấy', 5, count( $lay_php['rows'] ) );
teq( 'khu vực xếp theo seq', array( 'Z1', 'Z2' ), array_column( $lay_php['zones'], 'id' ) );
teq( 'dòng xếp theo seq', array( 'R1', 'R2', 'R3', 'R4', 'R5' ),
	array_column( $lay_php['rows'], 'id' ) );

// ============================================================ 13. QUYỀN Ở CỬA `lay()`
$nem = '';
try { VHJP_BaoCao::lay( $NV, 'KHONG-CO' ); } catch ( Throwable $e ) { $nem = $e->getMessage(); }
teq( 'mở báo cáo không tồn tại ⇒ nói rõ', 'Không tìm thấy báo cáo', $nem );

$la = array( 'id' => 'U8', 'hoTen' => 'Người lạ', 'role' => 'NHANVIEN',
	'machineType' => '', 'locationIds' => array( 'L2' ) );
$nem = '';
try { VHJP_BaoCao::lay( $la, 'RP20260901-0003' ); } catch ( Throwable $e ) { $nem = $e->getMessage(); }
teq( '🔴 nhân viên cơ sở KHÁC không mở được', 'Không có quyền với cơ sở này', $nem );
$g = goc( 'lay_bao_cao', array( array( 'the', 'RP20260901-0003' ) ), null, $la );
t( '🔴 và mã gốc cũng chặn đúng chỗ ấy',
	isset( $g['loi'] ) && false !== mb_strpos( $g['loi'], 'Không có quyền' ), $g );

$kt_ra = null; $nem = '';
try { $kt_ra = VHJP_BaoCao::lay( $KT, 'RP20260901-0003' ); } catch ( Throwable $e ) { $nem = $e->getMessage(); }
teq( '🔴 kế toán mở được mọi cơ sở', '', $nem );
teq( 'kế toán mở thì canEdit = false', false, $kt_ra['canEdit'] );
teq( '🔴 và KHÔNG nhận danh sách "trùng người khác" (đó là lời nhắn cho nhân viên)',
	array(), $kt_ra['trungNguoiKhac'] );

// ============================================================ 14. CỔNG ĐÃ KHAI HAI HÀM
require_once $plg . 'class-vhjp-cong.php';
$map = VHJP_Cong::map();
foreach ( array( 'jpMyReports', 'jpGetReport' ) as $fn ) {
	t( "cổng khai $fn", isset( $map[ $fn ] ), array_keys( $map ) );
	t( "$fn KHÔNG còn nằm ở bảng chưa làm", ! in_array( $fn, VHJP_Cong::chua_lam(), true ) );
}
t( '🔴 jpMyReports là màn của NHÂN VIÊN — phải có trong bảng gác vai',
	in_array( 'jpMyReports', VHJP_Cong::chi_nhan_vien(), true ), VHJP_Cong::chi_nhan_vien() );
t( '🔴 jpGetReport KHÔNG gác vai nhân viên — kế toán cũng phải mở được',
	! in_array( 'jpGetReport', VHJP_Cong::chi_nhan_vien(), true ) );

// ============================================================ kết
if ( $truot ) {
	echo "HỎNG: " . count( $truot ) . "\n";
	foreach ( $truot as $x ) { echo "  ✗ $x\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — đường đọc báo cáo khớp mã gốc chạy thật.\n";
