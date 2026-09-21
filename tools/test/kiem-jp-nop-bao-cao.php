<?php
/**
 * NỘP BÁO CÁO JP — `VHJP_BaoCao::nop()` · `thieu_chi_so()` · đường ĐẾM ẢNH
 * =============================================================================================
 *
 * 🔴 PHÉP CHẶN NỘP LÀ CON DAO HAI LƯỠI, VÀ CẢ HAI LƯỠI ĐỀU ĐÃ CẮT THẬT.
 *
 *    CHẶN THIẾU: để lọt một ô "chỉ số sau" trống ⇒ số đó là SỐ ĐẦU KỲ của kỳ sau, mà ô đầu
 *    kỳ BỊ KHOÁ nên không ai sửa được ⇒ kỳ sau nối ra 0 ⇒ CẢ CON SỐ TRÊN MẶT ĐỒNG HỒ thành
 *    doanh thu của một kỳ.
 *
 *    CHẶN THỪA: chặn theo một ô mà bảng ấy không có, hoặc chặn vì một dòng ma. Đã xảy ra hai
 *    lần, và lần nào cũng làm cả một cơ sở KHÔNG NỘP ĐƯỢC BÁO CÁO NÀO — không có cửa nào
 *    thoát, vì không có ô nào để điền cho hết chặn.
 *
 * Nên bài này đo CẢ HAI CHIỀU trên từng loại dòng, và đối chiếu với mã gốc chạy thật.
 *
 * Chạy: php tools/test/kiem-jp-nop-bao-cao.php
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

// ============================================================ 1. DÒNG MA
/*
 * 🔴 Đo bằng `> 0`, KHÔNG dùng "ô trống". Đây là chỗ bản gốc sai một lần và cái sai ấy chỉ
 * chữa được ĐÚNG MẪU TÁCH: phép tính dòng máy tiền ghi thẳng số 0 vào `amount`, nên với mẫu
 * CHUNG cứ LƯU một lần là dòng ma hoá thành "dòng có dữ liệu" và chặn nộp mãi mãi.
 */
$ca_ma = array(
	array( array() ),                                              // rỗng trơn
	array( array( 'amount' => 0, 'cash' => 0, 'soldQty' => 0 ) ),  // 🔴 đã LƯU một lần
	array( array( 'note' => 'ghi chú tay' ) ),                     // có ghi chú -> không phải ma
	array( array( 'machineId' => 'M1' ) ),                         // có danh tính
	array( array( 'mBefore' => 84855, 'mAfter' => 85710 ) ),       // 🔴 số THẬT, không danh tính
	array( array( 'xuDaysJson' => '[]' ) ),                        // máy chủ ghi cho MỌI dòng
	array( array( 'itemMisa' => 'MS1' ) ),
	array( null ),
);
$g = goc( 'dong_trong', $ca_ma, array() );
foreach ( $ca_ma as $i => $ca ) {
	teq( 'dong_trong(' . json_encode( $ca[0] ) . ')',
		isset( $g[ $i ] ) ? $g[ $i ] : null, VHJP_Tinh::dong_trong( $ca[0] ) );
}
teq( '🔴 dòng đã LƯU một lần (amount = 0) VẪN là dòng ma', true,
	VHJP_Tinh::dong_trong( array( 'amount' => 0, 'cash' => 0, 'soldQty' => 0 ) ) );
teq( '🔴 nhưng dòng có SỐ THẬT thì KHÔNG — bỏ qua nó là mất doanh thu cả kỳ', false,
	VHJP_Tinh::dong_trong( array( 'mBefore' => 84855, 'mAfter' => 85710 ) ) );
teq( 'chuỗi "[]" máy chủ tự ghi không làm dòng thôi là ma', true,
	VHJP_Tinh::dong_trong( array( 'xuDaysJson' => '[]' ) ) );

// ============================================================ 2. QR VƯỢT THÀNH TIỀN
$ca_qr = array(
	/* Mẫu TÁCH gõ tay: QR 9.5tr trên Thành tiền 5.92tr -> tiền phải nộp ÂM. */
	array( array( array( 'rowKind' => 'MAY', 'seq' => 1, 'itemCode' => 'H1',
		'bank' => 9500000, 'amount' => 5920000, 'mBefore' => 0 ) ) ),
	/* QR BẰNG Thành tiền: hợp lệ — cả ca khách quét mã hết. */
	array( array( array( 'rowKind' => 'MAY', 'seq' => 1, 'itemCode' => 'H1',
		'bank' => 500000, 'amount' => 500000, 'mBefore' => 0 ) ) ),
	/* Từng dòng đều ổn nhưng TỔNG thì không. */
	array( array(
		array( 'rowKind' => 'MAY', 'seq' => 1, 'itemCode' => 'H1', 'bank' => 100, 'amount' => 100, 'mBefore' => 0 ),
		array( 'rowKind' => 'MAY', 'seq' => 2, 'itemCode' => 'H2', 'bank' => 100, 'amount' => 0, 'mBefore' => 0 ),
	) ),
	/* Mẫu CHUNG: QR là khoản cộng THÊM, KHÔNG áp luật này. */
	array( array( array( 'rowKind' => 'MONEY', 'seq' => 1, 'itemCode' => 'H1',
		'bank' => 9500000, 'amount' => 100000 ) ) ),
	/* Dòng MAY đời cũ CÓ đồng hồ: cũng không áp. */
	array( array( array( 'rowKind' => 'MAY', 'seq' => 1, 'itemCode' => 'H1',
		'bank' => 900, 'amount' => 100, 'mBefore' => 50 ) ) ),
	array( array() ),
);
$g = goc( 'qr_vuot_tien', $ca_qr, array() );
foreach ( $ca_qr as $i => $ca ) {
	teq( "qr_vuot_tien[$i]", isset( $g[ $i ] ) ? $g[ $i ] : null,
		VHJP_Tinh::qr_vuot_tien( $ca[0] ) );
}
teq( '🔴 QR BẰNG Thành tiền là HỢP LỆ (chặn cả ca đó là chặn oan một ngày bán thật)',
	array(), VHJP_Tinh::qr_vuot_tien( array( array( 'rowKind' => 'MAY', 'seq' => 1,
		'itemCode' => 'H1', 'bank' => 500000, 'amount' => 500000, 'mBefore' => 0 ) ) ) );
teq( '🔴 mẫu CHUNG KHÔNG áp luật này — áp vào là chặn oan gần hết hệ thống',
	array(), VHJP_Tinh::qr_vuot_tien( array( array( 'rowKind' => 'MONEY', 'seq' => 1,
		'itemCode' => 'H1', 'bank' => 9500000, 'amount' => 100000 ) ) ) );
$ca_tong = VHJP_Tinh::qr_vuot_tien( array(
	array( 'rowKind' => 'MAY', 'seq' => 1, 'itemCode' => 'H1', 'bank' => 100, 'amount' => 100, 'mBefore' => 0 ),
	array( 'rowKind' => 'MAY', 'seq' => 2, 'itemCode' => 'H2', 'bank' => 100, 'amount' => 0, 'mBefore' => 0 ),
) );
/*
 * ⚠️ GHI LẠI MỘT QUAN SÁT, để lần sau không ai "dọn" nhầm: phép CẤP BÁO CÁO (Σ QR > Σ Thành
 * tiền) về mặt số học KHÔNG thể bắt thêm ca nào mà phép từng dòng bỏ lọt — mọi dòng đều
 * `qr ≤ tt` thì tổng cũng vậy. Bản gốc vẫn giữ nó, và bản này chép y nguyên: nó là một lưới
 * thứ hai rẻ tiền, và câu "CẢ BÁO CÁO" nói ra con số tổng mà người đọc cần thấy. Phép thử
 * dưới đây đòi CÓ MẶT câu ấy, không đòi nó là câu duy nhất.
 */
t( '🔴 phép CẤP BÁO CÁO có mặt, kèm con số tổng',
	false !== mb_strpos( implode( ' | ', $ca_tong ), 'CẢ BÁO CÁO' ), $ca_tong );

// ============================================================ 3. THIẾU CHỈ SỐ — SÁU LOẠI DÒNG
$ca_thieu = array(
	/* dòng máy tiền thiếu chỉ số sau */
	array( array( array( 'rowKind' => 'MONEY', 'seq' => 1, 'machineCode' => 'MAY1',
		'mBefore' => 100, 'mAfter' => '' ) ) ),
	/* đủ chỉ số -> không chặn */
	array( array( array( 'rowKind' => 'MONEY', 'seq' => 1, 'machineCode' => 'MAY1',
		'mBefore' => 100, 'mAfter' => 110 ) ) ),
	/* 🔴 DÒNG MA -> KHÔNG chặn */
	array( array( array( 'rowKind' => 'MONEY', 'seq' => 16, 'amount' => 0 ) ) ),
	/* 🔴 dòng máy xu thiếu chỉ số COIN nhưng CÓ chỉ số MONEY -> câu phải nói đúng thứ thiếu */
	array( array( array( 'rowKind' => 'COIN', 'seq' => 1, 'machineCode' => 'XU1',
		'mBefore' => 84855, 'mAfter' => 85710, 'cBefore' => 16971, 'cAfter' => '' ) ) ),
	/* 🔴 dòng KHÔNG danh tính nhưng có số thật -> phải nói "chưa chọn ô máy" */
	array( array( array( 'rowKind' => 'COIN', 'seq' => 1,
		'mBefore' => 84855, 'mAfter' => 85710, 'cBefore' => 16971, 'cAfter' => 100 ) ) ),
	/* bảng tồn kho / kho ngoài KHÔNG có đồng hồ -> không chặn */
	array( array( array( 'rowKind' => 'STOCK', 'seq' => 1, 'itemCode' => 'H1', 'stockOpen' => 5 ) ) ),
	array( array( array( 'rowKind' => 'NGOAI', 'seq' => 1, 'itemCode' => 'H1', 'stockOpen' => 5 ) ) ),
	/* 🔴 dòng HÀNG mẫu tách: chặn theo SỐ ĐÃ BÁN, không theo ô đếm tồn */
	array( array( array( 'rowKind' => 'HANG', 'seq' => 1, 'itemCode' => 'H1',
		'soldQty' => '', 'stockActual' => '', 'stockOpen' => 5 ) ) ),
	array( array( array( 'rowKind' => 'HANG', 'seq' => 1, 'itemCode' => 'H1',
		'soldQty' => 3, 'stockActual' => '', 'stockOpen' => 5 ) ) ),
	/* 🔴 dòng MÁY gõ tay: ba ô tiền, KHÔNG đòi đồng hồ */
	array( array( array( 'rowKind' => 'MAY', 'seq' => 1, 'itemCode' => 'H1', 'mBefore' => 0,
		'amount' => '', 'cash' => '', 'cashReal' => '', 'bank' => '' ) ) ),
	array( array( array( 'rowKind' => 'MAY', 'seq' => 1, 'itemCode' => 'H1', 'mBefore' => 0,
		'amount' => 100, 'cash' => 100, 'cashReal' => 90, 'bank' => '' ) ) ),
	/* 🔴 dòng MÁY đời cũ CÓ đồng hồ: chặn theo đồng hồ, không đòi ba ô kia */
	array( array( array( 'rowKind' => 'MAY', 'seq' => 1, 'itemCode' => 'H1', 'mBefore' => 50,
		'mAfter' => 60, 'amount' => '', 'cash' => '', 'cashReal' => '' ) ) ),
	/* 🔴 dòng MAY gõ tay KHÔNG danh tính -> phải nói "mã hàng", KHÔNG nói "ô máy" */
	array( array( array( 'rowKind' => 'MAY', 'seq' => 9, 'mBefore' => 0,
		'amount' => 100, 'cash' => 100, 'cashReal' => 90 ) ) ),
);
$g = goc( 'thieu_chi_so', $ca_thieu, array() );
foreach ( $ca_thieu as $i => $ca ) {
	teq( "thieu_chi_so[$i]", isset( $g[ $i ] ) ? $g[ $i ] : null,
		VHJP_BaoCao::thieu_chi_so( $ca[0] ) );
}
$tc = function ( $r ) { return VHJP_BaoCao::thieu_chi_so( array( $r ) ); };
teq( '🔴 DÒNG MA không chặn nộp — chặn là cả cơ sở kẹt cứng không có cửa thoát',
	array(), $tc( array( 'rowKind' => 'MONEY', 'seq' => 16, 'amount' => 0 ) ) );
$xu = $tc( array( 'rowKind' => 'COIN', 'seq' => 1, 'machineCode' => 'XU1',
	'mBefore' => 84855, 'mAfter' => 85710, 'cBefore' => 16971, 'cAfter' => '' ) );
teq( '🔴 thiếu đúng chỉ số COIN thì nói ĐÚNG là COIN', array( 'XU1 (COIN)' ), $xu );
$ko_ten = $tc( array( 'rowKind' => 'MAY', 'seq' => 9, 'mBefore' => 0,
	'amount' => 100, 'cash' => 100, 'cashReal' => 90 ) );
t( '🔴 mẫu TÁCH · dòng chưa chọn thì bảo chọn MÃ HÀNG (bảng ấy không có cột ô máy)',
	1 === count( $ko_ten ) && false !== mb_strpos( $ko_ten[0], 'mã hàng' ), $ko_ten );
$ko_ten2 = $tc( array( 'rowKind' => 'MONEY', 'seq' => 9, 'mBefore' => 0, 'mAfter' => 5 ) );
t( 'còn mẫu CHUNG thì bảo chọn Ô MÁY',
	false !== mb_strpos( implode( ' ', $ko_ten2 ), 'ô máy' ), $ko_ten2 );
teq( '🔴 dòng MÁY gõ tay KHÔNG bị đòi chỉ số đồng hồ (bảng ấy không còn ô đó)',
	array(), $tc( array( 'rowKind' => 'MAY', 'seq' => 1, 'itemCode' => 'H1', 'mBefore' => 0,
		'amount' => 100, 'cash' => 100, 'cashReal' => 90, 'bank' => '' ) ) );
teq( '🔴 dòng HÀNG chặn theo SỐ ĐÃ BÁN, KHÔNG theo ô đếm tồn (ô đếm là tuỳ chọn)',
	array( 'H1 (số đã bán)' ),
	$tc( array( 'rowKind' => 'HANG', 'seq' => 1, 'itemCode' => 'H1',
		'soldQty' => '', 'stockActual' => '', 'stockOpen' => 5 ) ) );
teq( 'và có số đã bán rồi thì thôi, dù ô đếm vẫn trống', array(),
	$tc( array( 'rowKind' => 'HANG', 'seq' => 1, 'itemCode' => 'H1',
		'soldQty' => 3, 'stockActual' => '', 'stockOpen' => 5 ) ) );

// ============================================================ 4. SỔ THỬ CHO ĐƯỜNG NỘP
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
		array( 'id' => 'C1', 'locationId' => 'L1', 'name' => 'Cụm cửa chính',
			'hasQR' => 'Y', 'photoCount' => 1, 'active' => 'Y' ),
	),
	'JP_Machines' => array(
		array( 'id' => 'M1', 'locationId' => 'L1', 'clusterId' => 'C1', 'code' => 'MAY-01',
			'itemCode' => '100JP031', 'photoCount' => 1, 'active' => 'Y' ),
	),
	'JP_Reports' => array(
		array( 'id' => 'RP-A', 'locationId' => 'L1', 'locationName' => 'AEON Bình Dương',
			'machineType' => 'TIEN', 'bcMau' => '', 'fromDate' => '2026-09-01',
			'toDate' => '2026-09-15', 'userId' => 'U1', 'userName' => 'Nam', 'status' => 'NHAP',
			'adjMachine' => 0, 'refundCustomer' => 0, 'totalSubmit' => 500000,
			'rejectReason' => 'lần trước sai số', 'rejectBy' => 'ketoan', 'rejectPart' => 'ALL' ),
		/* Báo cáo RỖNG — không có dòng nào. */
		array( 'id' => 'RP-RONG', 'locationId' => 'L1', 'machineType' => 'TIEN', 'bcMau' => '',
			'fromDate' => '2026-08-01', 'toDate' => '2026-08-15', 'userId' => 'U1',
			'userName' => 'Nam', 'status' => 'NHAP' ),
		/* Có lệch máy mà CHƯA ghi lý do. */
		array( 'id' => 'RP-LECH', 'locationId' => 'L1', 'machineType' => 'TIEN', 'bcMau' => '',
			'fromDate' => '2026-07-01', 'toDate' => '2026-07-15', 'userId' => 'U1',
			'userName' => 'Nam', 'status' => 'NHAP', 'adjMachine' => -50000,
			'adjMachineNote' => '', 'refundCustomer' => 0 ),
		/* Có hoàn khách mà CHƯA ghi lý do. */
		array( 'id' => 'RP-HOAN', 'locationId' => 'L1', 'machineType' => 'TIEN', 'bcMau' => '',
			'fromDate' => '2026-06-01', 'toDate' => '2026-06-15', 'userId' => 'U1',
			'userName' => 'Nam', 'status' => 'NHAP', 'adjMachine' => 0,
			'refundCustomer' => 30000, 'refundNote' => '' ),
		/* Còn ô chỉ số trống. */
		array( 'id' => 'RP-THIEU', 'locationId' => 'L1', 'machineType' => 'TIEN', 'bcMau' => '',
			'fromDate' => '2026-05-01', 'toDate' => '2026-05-15', 'userId' => 'U1',
			'userName' => 'Nam', 'status' => 'NHAP', 'adjMachine' => 0, 'refundCustomer' => 0 ),
		/* Đã nộp rồi. */
		array( 'id' => 'RP-DANOP', 'locationId' => 'L1', 'machineType' => 'TIEN', 'bcMau' => '',
			'fromDate' => '2026-04-01', 'toDate' => '2026-04-15', 'userId' => 'U1',
			'userName' => 'Nam', 'status' => 'CHO_DUYET' ),
	),
	'JP_Zones' => array(
		array( 'id' => 'RP-A-Z1', 'reportId' => 'RP-A', 'seq' => 1, 'name' => 'Tầng 1',
			'clusterId' => 'C1', 'note' => '' ),
		array( 'id' => 'RP-THIEU-Z1', 'reportId' => 'RP-THIEU', 'seq' => 1, 'name' => 'Tầng 1',
			'clusterId' => 'C1', 'note' => '' ),
		array( 'id' => 'RP-LECH-Z1', 'reportId' => 'RP-LECH', 'seq' => 1, 'name' => 'Tầng 1',
			'clusterId' => 'C1', 'note' => '' ),
		array( 'id' => 'RP-HOAN-Z1', 'reportId' => 'RP-HOAN', 'seq' => 1, 'name' => 'Tầng 1',
			'clusterId' => 'C1', 'note' => '' ),
	),
	'JP_Rows' => array(
		array( 'id' => 'RP-A-R1', 'reportId' => 'RP-A', 'zoneId' => 'RP-A-Z1', 'seq' => 1,
			'rowKind' => 'MONEY', 'machineId' => 'M1', 'machineCode' => 'MAY-01',
			'itemCode' => '100JP031', 'price' => 100000, 'mBefore' => 100, 'mAfter' => 110,
			'stockActual' => 5, 'bank' => 200000 ),
		array( 'id' => 'RP-THIEU-R1', 'reportId' => 'RP-THIEU', 'zoneId' => 'RP-THIEU-Z1', 'seq' => 1,
			'rowKind' => 'MONEY', 'machineId' => 'M1', 'machineCode' => 'MAY-01',
			'itemCode' => '100JP031', 'mBefore' => 100, 'mAfter' => null ),
		array( 'id' => 'RP-LECH-R1', 'reportId' => 'RP-LECH', 'zoneId' => 'RP-LECH-Z1', 'seq' => 1,
			'rowKind' => 'MONEY', 'machineId' => 'M1', 'machineCode' => 'MAY-01',
			'itemCode' => '100JP031', 'mBefore' => 100, 'mAfter' => 110 ),
		array( 'id' => 'RP-HOAN-R1', 'reportId' => 'RP-HOAN', 'zoneId' => 'RP-HOAN-Z1', 'seq' => 1,
			'rowKind' => 'MONEY', 'machineId' => 'M1', 'machineCode' => 'MAY-01',
			'itemCode' => '100JP031', 'mBefore' => 100, 'mAfter' => 110 ),
	),
	'JP_Photos' => array(),
);
foreach ( $SO as $tab => $ds ) {
	foreach ( $ds as $hang ) {
		$k = isset( $hang['id'] ) ? $hang['id'] : $hang['code'];
		t( "nạp được $tab#$k", false !== VHJP_Nguon::them( $tab, $hang ), VHJP_Nguon::loi_cuoi() );
	}
}
$NV = array( 'id' => 'U1', 'hoTen' => 'Nam', 'role' => 'NHANVIEN', 'machineType' => '',
	'locationIds' => array( 'L1' ) );
$KT = array( 'id' => 'U9', 'hoTen' => 'Kế toán', 'role' => 'KETOAN', 'machineType' => '',
	'locationIds' => array() );

// ============================================================ 5. ĐẾM ẢNH
$g = goc( 'anh_tien_do', array( array( 'the', 'RP-A' ) ), so_tu_csdl(), $NV );
$td_goc = isset( $g[0] ) ? $g[0] : $g;
$td_php = VHJP_Anh::tien_do( $NV, 'RP-A' );
chieu( 'tiến độ ảnh', $td_goc, $td_php, array( 'warns' ) );
if ( isset( $td_goc['warns'] ) ) {
	teq( 'tiến độ ảnh · đúng bộ câu cảnh báo', array_column( $td_goc['warns'], 'detail' ),
		array_column( $td_php['warns'], 'detail' ) );
}
teq( '🔴 cần 2 ảnh (1 chỉ số máy + 1 Pay Box của cụm)', 2, $td_php['need'] );
teq( 'chưa có tấm nào', 0, $td_php['got'] );
teq( 'và thiếu đúng 2 chỗ', 2, $td_php['missing'] );

/* 🔴 W17: có tiền QR mà KHÔNG khu nào chọn được cụm QR. */
VHJP_Nguon::sua( 'JP_Zones', 'RP-A-Z1', array( 'clusterId' => '' ) );
$td2 = VHJP_Anh::tien_do( $NV, 'RP-A' );
$ma_w = array_column( $td2['warns'], 'code' );
t( '🔴 có tiền QR mà chưa khu nào chọn cụm ⇒ W17', in_array( 'W17', $ma_w, true ), $ma_w );
teq( '🔴 nhưng W17 KHÔNG đếm vào số "chỗ thiếu ảnh" — nó không phải một ô ảnh trống',
	1, $td2['missing'] );
$g = goc( 'anh_tien_do', array( array( 'the', 'RP-A' ) ), so_tu_csdl(), $NV );
chieu( '🔴 và mã gốc cũng vậy', isset( $g[0] ) ? $g[0] : $g, $td2, array( 'warns' ) );
VHJP_Nguon::sua( 'JP_Zones', 'RP-A-Z1', array( 'clusterId' => 'C1' ) );

/* Không có tiền QR thì KHÔNG kêu — không cần bằng chứng cho khoản không tồn tại. */
VHJP_Nguon::sua( 'JP_Rows', 'RP-A-R1', array( 'bank' => 0 ) );
VHJP_Nguon::sua( 'JP_Zones', 'RP-A-Z1', array( 'clusterId' => '' ) );
$ma_w = array_column( VHJP_Anh::tien_do( $NV, 'RP-A' )['warns'], 'code' );
t( '🔴 không có tiền QR ⇒ KHÔNG kêu W17 (báo oan vài lần là không ai đọc nữa)',
	! in_array( 'W17', $ma_w, true ), $ma_w );
VHJP_Nguon::sua( 'JP_Rows', 'RP-A-R1', array( 'bank' => 200000 ) );
VHJP_Nguon::sua( 'JP_Zones', 'RP-A-Z1', array( 'clusterId' => 'C1' ) );

/* Cơ sở CHƯA cấu hình cụm QR nào thì cũng không kêu — kêu vào chỗ nhân viên không sửa được. */
VHJP_Nguon::sua( 'JP_Clusters', 'C1', array( 'hasQR' => 'N' ) );
VHJP_Nguon::sua( 'JP_Zones', 'RP-A-Z1', array( 'clusterId' => '' ) );
$ma_w = array_column( VHJP_Anh::tien_do( $NV, 'RP-A' )['warns'], 'code' );
t( '🔴 cơ sở chưa có cụm QR nào ⇒ cũng KHÔNG kêu (nhân viên không có gì để chọn)',
	! in_array( 'W17', $ma_w, true ), $ma_w );
VHJP_Nguon::sua( 'JP_Clusters', 'C1', array( 'hasQR' => 'Y' ) );
VHJP_Nguon::sua( 'JP_Zones', 'RP-A-Z1', array( 'clusterId' => 'C1' ) );

teq( '🔴 kế toán cũng xem được tiến độ ảnh (đó là căn cứ để trả về)',
	2, VHJP_Anh::tien_do( $KT, 'RP-A' )['need'] );

// ============================================================ 6. 🔴 NỘP THẬT
/* ⚠️ CHỤP SỔ TRƯỚC KHI PHP NỘP. Chụp sau là bên node nhận một báo cáo đã ở trạng thái
   CHO_DUYET và chối ngay — phép đối chiếu lúc ấy đo cái khác chứ không đo hàm. */
$so_truoc = so_tu_csdl();
$php_ra = VHJP_BaoCao::nop( $NV, 'RP-A' );
$g = goc( 'nop', array( array( 'the', 'RP-A' ) ), $so_truoc, $NV );
chieu( '🔴 nộp · khớp mã gốc', isset( $g[0] ) ? $g[0] : $g, $php_ra );

$sau = VHJP_Nguon::tim_mot( 'JP_Reports', 'id', 'RP-A' );
teq( '🔴 trạng thái chuyển sang CHỜ DUYỆT', 'CHO_DUYET', VHJP_Doc::str( $sau['status'] ) );
t( 'và ghi lại thời điểm nộp', '' !== VHJP_Doc::str( $sau['submittedAt'] ), $sau['submittedAt'] );
teq( '🔴 XOÁ dấu vết lần bị trả về — không thì kế toán mở ra thấy lý do từ chối cũ',
	'', VHJP_Doc::str( $sau['rejectReason'] ) );
teq( 'cả người trả về', '', VHJP_Doc::str( $sau['rejectBy'] ) );
t( '🔴 CHỐT cảnh báo thiếu ảnh vào báo cáo — không thì kế toán không có căn cứ trả về',
	'' !== VHJP_Doc::str( $sau['photoWarnJson'] ), $sau['photoWarnJson'] );
$pw = json_decode( $sau['photoWarnJson'], true );
teq( 'và chốt đúng 2 chỗ thiếu', 2, count( $pw ) );
teq( '🔴 thiếu ảnh KHÔNG chặn nộp — mạng ở mall hay hỏng, chặn là kẹt cả buổi',
	true, $php_ra['ok'] );
t( 'nhưng có nói ra', 2 === $php_ra['thieuAnh']
	&& false !== mb_strpos( $php_ra['msg'], 'thiếu 2 chỗ ảnh' ), $php_ra );
t( 'câu báo nêu kỳ theo lối ngày/tháng/năm',
	false !== mb_strpos( $php_ra['msg'], '01/09/2026' ), $php_ra['msg'] );
$nk = VHJP_Nguon::tim( 'JP_Audit', 'action', 'REPORT_SUBMIT' );
t( '🔴 có ghi nhật ký lượt nộp', count( $nk ) > 0, count( $nk ) );

// ============================================================ 7. BỐN PHÉP CHẶN
$nem = function ( $f ) { try { $f(); return ''; } catch ( Throwable $e ) { return $e->getMessage(); } };
$chan = function ( $ma ) use ( $NV, $nem ) {
	return $nem( function () use ( $NV, $ma ) { VHJP_BaoCao::nop( $NV, $ma ); } );
};
teq( '① chưa có dòng nào', 'Chưa có dòng nào để nộp', $chan( 'RP-RONG' ) );

$loi = $chan( 'RP-THIEU' );
t( '② còn ô chỉ số sau bỏ trống ⇒ CHẶN', false !== mb_strpos( $loi, 'chưa nhập chỉ số sau' ), $loi );
t( '② và NÓI RA HẬU QUẢ (sai số đầu kỳ của kỳ sau), không chỉ nói trạng thái',
	false !== mb_strpos( $loi, 'sai số đầu kỳ của kỳ sau' ), $loi );
t( '② nêu đích danh ô nào', false !== mb_strpos( $loi, 'MAY-01' ), $loi );
teq( 'và báo cáo VẪN là nháp', 'NHAP',
	VHJP_Doc::str( VHJP_Nguon::tim_mot( 'JP_Reports', 'id', 'RP-THIEU' )['status'] ) );

teq( '④ có lệch máy mà chưa ghi lý do', 'Nhập lý do cho khoản lệch máy', $chan( 'RP-LECH' ) );
teq( '④ có hoàn khách mà chưa ghi lý do', 'Nhập lý do cho khoản hoàn khách', $chan( 'RP-HOAN' ) );
/* Ghi lý do rồi thì nộp được. */
VHJP_Nguon::sua( 'JP_Reports', 'RP-LECH', array( 'adjMachineNote' => 'máy nuốt tiền' ) );
teq( 'ghi lý do rồi thì nộp được', true, VHJP_BaoCao::nop( $NV, 'RP-LECH' )['ok'] );

/* ③ QR vượt Thành tiền — dựng một báo cáo mẫu TÁCH. */
VHJP_Nguon::them( 'JP_Reports', array( 'id' => 'RP-QR', 'locationId' => 'L1',
	'machineType' => 'TIEN', 'bcMau' => 'TACH', 'fromDate' => '2026-03-01',
	'toDate' => '2026-03-15', 'userId' => 'U1', 'userName' => 'Nam', 'status' => 'NHAP',
	'adjMachine' => 0, 'refundCustomer' => 0 ) );
VHJP_Nguon::them( 'JP_Zones', array( 'id' => 'RP-QR-Z1', 'reportId' => 'RP-QR', 'seq' => 1,
	'name' => 'K1', 'clusterId' => 'C1' ) );
VHJP_Nguon::them( 'JP_Rows', array( 'id' => 'RP-QR-R1', 'reportId' => 'RP-QR',
	'zoneId' => 'RP-QR-Z1', 'seq' => 1, 'rowKind' => 'MAY', 'itemCode' => '100JP031',
	'mBefore' => 0, 'amount' => 5920000, 'cash' => 100000, 'cashReal' => 100000,
	'bank' => 9500000 ) );
$loi = $chan( 'RP-QR' );
t( '③ QR lớn hơn Thành tiền ⇒ CHẶN', false !== mb_strpos( $loi, 'QR lớn hơn Thành tiền' ), $loi );
t( '③ và NÓI RA HẬU QUẢ (tiền phải nộp ra ÂM, cơ sở coi như không phải nộp gì)',
	false !== mb_strpos( $loi, 'ÂM' ), $loi );

/* 🔴 THỨ TỰ: thiếu số thì nhắc thiếu TRƯỚC, đừng bắt giải thích một con số sai. */
VHJP_Nguon::sua( 'JP_Reports', 'RP-QR', array( 'adjMachine' => -1000, 'adjMachineNote' => '' ) );
VHJP_Nguon::sua( 'JP_Rows', 'RP-QR-R1', array( 'amount' => null ) );
$loi = $chan( 'RP-QR' );
t( '🔴 thiếu số thì nhắc THIẾU trước, chưa nhắc QR và chưa đòi lý do',
	false !== mb_strpos( $loi, 'chưa nhập chỉ số sau' ), $loi );
VHJP_Nguon::sua( 'JP_Rows', 'RP-QR-R1', array( 'amount' => 5920000 ) );
$loi = $chan( 'RP-QR' );
t( '🔴 đủ số rồi thì nhắc QR, VẪN chưa đòi lý do lệch máy — số lệch tính từ chính mấy ô ấy',
	false !== mb_strpos( $loi, 'QR lớn hơn' ), $loi );

// ============================================================ 8. QUYỀN
teq( 'báo cáo không tồn tại', 'Không tìm thấy báo cáo', $chan( 'KHONGCO' ) );
teq( '🔴 báo cáo ĐÃ NỘP thì không nộp lại', 'Báo cáo không ở trạng thái nộp được',
	$chan( 'RP-DANOP' ) );
$NV2 = array( 'id' => 'U2', 'hoTen' => 'Bình', 'role' => 'NHANVIEN', 'machineType' => '',
	'locationIds' => array( 'L1' ) );
$loi = $nem( function () use ( $NV2 ) { VHJP_BaoCao::nop( $NV2, 'RP-HOAN' ); } );
t( '🔴 không nộp hộ báo cáo của người khác', false !== mb_strpos( $loi, 'Nam' ), $loi );

// ============================================================ 9. CỔNG
require_once $plg . 'class-vhjp-cong.php';
foreach ( array( 'jpSubmitReport', 'jpPhotoProgress' ) as $fn ) {
	t( "cổng khai $fn", isset( VHJP_Cong::map()[ $fn ] ) );
	t( "$fn không còn ở bảng chưa làm", ! in_array( $fn, VHJP_Cong::chua_lam(), true ) );
}
t( '🔴 jpSubmitReport gác vai NHÂN VIÊN',
	in_array( 'jpSubmitReport', VHJP_Cong::chi_nhan_vien(), true ) );
t( '🔴 jpPhotoProgress KHÔNG gác vai nhân viên — kế toán cũng phải thấy còn thiếu mấy ảnh',
	! in_array( 'jpPhotoProgress', VHJP_Cong::chi_nhan_vien(), true ) );

// ============================================================ kết
if ( $truot ) {
	echo "HỎNG: " . count( $truot ) . "\n";
	foreach ( $truot as $x ) { echo "  ✗ $x\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — đường nộp báo cáo và đếm ảnh khớp mã gốc chạy thật.\n";
