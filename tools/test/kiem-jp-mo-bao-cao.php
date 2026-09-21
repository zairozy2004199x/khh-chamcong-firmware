<?php
/**
 * TẠO / MỞ BÁO CÁO JP — `VHJP_BaoCao::mo()` và đường GIEO DÒNG
 * =============================================================================================
 *
 * 🔴 GIEO DÒNG LÀ CHỖ DỄ MẤT TIỀN NHẤT CỦA CẢ BỘ, VÀ MẤT MỘT CÁCH IM LẶNG.
 *    Gieo là chép bố cục kỳ trước sang kỳ mới. Chép THIẾU thì nhân viên phải gõ lại 54 mã —
 *    khó chịu, nhưng thấy ngay. Chép THỪA thì tệ hơn nhiều: mang theo một con số PHÁT SINH của
 *    kỳ trước (chỉ số sau đồng hồ, đã bán, trả kho, hoàn khách) là nhân viên bấm Nộp mà không
 *    sửa gì cũng ra một báo cáo TRÔNG ĐẦY ĐỦ — doanh thu kỳ này bằng kỳ trước, sổ vẫn cân,
 *    không phép kiểm kế toán nào đỏ.
 *
 * Bài này nạp CÙNG một bộ dữ liệu vào hai nơi (cơ sở dữ liệu PHP, và sổ giả của mã gốc chạy
 * bằng node), cho CẢ HAI cùng tạo một báo cáo mang CÙNG MỘT MÃ, rồi đòi hai bên ra y hệt —
 * từng dòng, từng trường.
 *
 * ---------------------------------------------------------------------------------------------
 * BA VẾ CỦA LUẬT "BỎ DÒNG ĐÃ TRẢ KHO", ĐO RIÊNG TỪNG VẾ
 * ---------------------------------------------------------------------------------------------
 *   ① chỉ dòng giữ HÀNG · ② tồn cuối = 0 · ③ kỳ trước CÓ trả kho
 * Sổ thử dưới đây có đủ ba ca ngược nhau, vì bỏ sót vế nào cũng hỏng theo một kiểu riêng:
 *   · bỏ vế ① ⇒ MẤT LUÔN Ô MÁY khỏi kỳ sau (dòng MONEY mang cả tiền lẫn hàng);
 *   · bỏ vế ③ ⇒ BÁN HẾT CŨNG BỊ BỎ, mà bán hết là chuyện thường.
 *
 * Chạy: php tools/test/kiem-jp-mo-bao-cao.php
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

// ============================================================ 1. SỔ THỬ
$SO = array(
	'JP_Locations' => array(
		array( 'id' => 'L1', 'code' => 'AMBD', 'name' => 'AEON Bình Dương', 'maKH' => 'KH00119',
			'machineType' => 'TIEN', 'bcMau' => '', 'coDhTrung' => '', 'chonGiaXung' => 'Y',
			'active' => 'Y' ),
		array( 'id' => 'L2', 'code' => 'MOI', 'name' => 'Cơ sở mới toanh', 'maKH' => 'KH00200',
			'machineType' => 'TIEN', 'bcMau' => '', 'coDhTrung' => '', 'chonGiaXung' => '',
			'active' => 'Y' ),
		array( 'id' => 'L3', 'code' => 'XU', 'name' => 'Cơ sở máy xu', 'maKH' => 'KH00300',
			'machineType' => 'XU', 'bcMau' => '', 'coDhTrung' => '', 'chonGiaXung' => '',
			'active' => 'Y' ),
		/* Cơ sở khai mẫu TÁCH — cần một cơ sở như vậy để đo được luật "máy XU luôn dùng mẫu
		   CHUNG". Ở cơ sở mẫu chung thì hai luật cho ra cùng một kết quả, nên không đo được. */
		array( 'id' => 'L4', 'code' => 'SBPQ', 'name' => 'Sân bay Phú Quốc', 'maKH' => 'KH00400',
			'machineType' => 'TIEN', 'bcMau' => 'TACH', 'coDhTrung' => '', 'chonGiaXung' => '',
			'active' => 'Y' ),
	),
	'JP_Reports' => array(
		/* Kỳ trước ĐÃ DUYỆT — khuôn để gieo. */
		array( 'id' => 'RP20260801-0001', 'locationId' => 'L1', 'locationName' => 'AEON Bình Dương',
			'maKH' => 'KH00119', 'machineType' => 'TIEN', 'bcMau' => '',
			'fromDate' => '2026-08-01', 'toDate' => '2026-08-31', 'userId' => 'U1',
			'userName' => 'Nam', 'status' => 'HOAN_TAT', 'revMeter' => 5000000,
			'totalSubmit' => 4700000, 'warnCount' => 1 ),
	),
	'JP_Zones' => array(
		array( 'id' => 'ZA', 'reportId' => 'RP20260801-0001', 'seq' => 1, 'name' => 'Tầng 1',
			'clusterId' => 'C1', 'note' => 'ghi chú KHÔNG được mang sang' ),
		array( 'id' => 'ZB', 'reportId' => 'RP20260801-0001', 'seq' => 2, 'name' => '',
			'clusterId' => 'C2', 'note' => '' ),
	),
	'JP_Rows' => array(
		/* ① Ô MÁY bình thường — chỉ số sau 1360 thành chỉ số TRƯỚC của kỳ mới. */
		array( 'id' => 'RA1', 'reportId' => 'RP20260801-0001', 'zoneId' => 'ZA', 'seq' => 1,
			'rowKind' => 'MONEY', 'machineId' => 'M1', 'machineCode' => '100JP111',
			'itemCode' => '100JP111', 'itemMisa' => 'MS111', 'itemName' => 'Trứng 20k',
			'price' => 20000, 'mBefore' => 1300, 'mAfter' => 1360, 'mActual' => 60,
			'collection' => 30, 'amount' => 1200000, 'cash' => 900000, 'bank' => 300000,
			'hOpen' => 51, 'hBefore' => 51, 'hAfter' => 54, 'soldQty' => 3, 'hLeft' => 48,
			'stockActual' => 48, 'stockLeftCalc' => 48, 'returnQty' => 0, 'giaXung' => 10000,
			'refundAmt' => 40000, 'refundQty' => 2, 'refundRowNote' => 'trả khách',
			'topupNote' => 'nạp thêm', 'note' => 'ghi chú cũ' ),
		/* ② 🔴 Ô MÁY tồn cuối 0 VÀ CÓ trả kho — vẫn PHẢI gieo. Bỏ nó là mất luôn ô máy. */
		array( 'id' => 'RA2', 'reportId' => 'RP20260801-0001', 'zoneId' => 'ZA', 'seq' => 2,
			'rowKind' => 'MONEY', 'machineId' => 'M2', 'machineCode' => '150JP113',
			'itemCode' => '150JP113', 'itemMisa' => 'MS113', 'price' => 15000,
			'mBefore' => 700, 'mAfter' => 730, 'stockActual' => 0, 'stockLeftCalc' => 0,
			'returnQty' => 5, 'giaXung' => null ),
		/* ③ 🔴 Dòng HÀNG tồn cuối 0 VÀ CÓ trả kho — ĐÚNG ca phải bỏ. */
		array( 'id' => 'RA3', 'reportId' => 'RP20260801-0001', 'zoneId' => 'ZB', 'seq' => 3,
			'rowKind' => 'NGOAI', 'itemCode' => 'H-TRAKHO', 'itemMisa' => 'MSTK',
			'itemName' => 'Gấu bông đã trả kho', 'price' => 30000,
			'stockOpen' => 10, 'stockActual' => 0, 'stockLeftCalc' => 0, 'returnQty' => 10 ),
		/* ④ 🔴 Dòng HÀNG BÁN HẾT (tồn 0, KHÔNG trả kho) — vẫn PHẢI gieo. */
		array( 'id' => 'RA4', 'reportId' => 'RP20260801-0001', 'zoneId' => 'ZB', 'seq' => 4,
			'rowKind' => 'NGOAI', 'itemCode' => 'H-BANHET', 'itemMisa' => 'MSBH',
			'itemName' => 'Gấu bông bán hết', 'price' => 25000,
			'stockOpen' => 8, 'stockActual' => 0, 'stockLeftCalc' => 0, 'returnQty' => 0 ),
		/* ⑤ 🔴 Dòng HÀNG nhân viên KHÔNG ĐẾM (stockActual trống) nhưng web tính còn 33 —
		       tồn đầu kỳ sau phải là 33, không phải 0. */
		array( 'id' => 'RA5', 'reportId' => 'RP20260801-0001', 'zoneId' => 'ZB', 'seq' => 5,
			'rowKind' => 'NGOAI', 'itemCode' => 'H-KHONGDEM', 'itemMisa' => 'MSKD',
			'itemName' => 'Mã không ai đếm', 'price' => 12000,
			'stockOpen' => 40, 'stockActual' => null, 'stockLeftCalc' => 33, 'returnQty' => 0 ),
	),
);
foreach ( $SO as $tab => $ds ) {
	foreach ( $ds as $hang ) {
		t( "nạp được $tab#" . $hang['id'], false !== VHJP_Nguon::them( $tab, $hang ),
			VHJP_Nguon::loi_cuoi() );
	}
}

$NV  = array( 'id' => 'U1', 'hoTen' => 'Nam', 'role' => 'NHANVIEN', 'machineType' => '',
	'locationIds' => array( 'L1', 'L2', 'L3', 'L4' ) );
$NV_XU = array( 'id' => 'U3', 'hoTen' => 'Xu', 'role' => 'NHANVIEN', 'machineType' => 'XU',
	'locationIds' => array( 'L1', 'L4' ) );
$KT  = array( 'id' => 'U9', 'hoTen' => 'Kế toán', 'role' => 'KETOAN', 'machineType' => '',
	'locationIds' => array() );

function goc( $ten, $ca, $so, $ai = null, $tra_so = false ) {
	global $cau_noi;
	$ra = array(); $ma = 0;
	$lenh = ( $tra_so ? 'JP_TRA_SO=1 ' : '' )
		. 'node ' . escapeshellarg( $cau_noi ) . ' ' . escapeshellarg( $ten ) . ' '
		. escapeshellarg( wp_json_encode( $ca ) ) . ' ' . escapeshellarg( wp_json_encode( $so ) );
	if ( null !== $ai ) { $lenh .= ' ' . escapeshellarg( wp_json_encode( $ai ) ); }
	exec( $lenh . ' 2>&1', $ra, $ma );
	if ( 0 !== $ma ) { return array( 'loi' => implode( "\n", $ra ) ); }
	return json_decode( implode( '', $ra ), true );
}

/**
 * Chụp lại SỔ THẬT của bản PHP để đưa sang mã gốc.
 *
 * ⚠️ Từ mục 5 trở đi, cơ sở dữ liệu đã có thêm mấy báo cáo do chính bài kiểm tạo ra. Đưa sang
 *    node một bộ chép cứng là hai bên đứng ở hai điểm xuất phát khác nhau — phép đối chiếu
 *    lúc ấy đo cái khác chứ không đo hàm. Chụp thẳng từ sổ thì bao giờ cũng cùng một điểm.
 */
function so_tu_csdl() {
	$ra = array();
	foreach ( array( 'JP_Locations', 'JP_Reports', 'JP_Zones', 'JP_Rows', 'JP_Photos' ) as $tab ) {
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

// ============================================================ 2. 🔴 TẠO BÁO CÁO, HAI BÊN CÙNG MÃ
$php_ra = VHJP_BaoCao::mo( $NV, 'L1', '2026-09-01', '2026-09-15', 'TIEN' );
t( '🔴 tạo được báo cáo mới', ! empty( $php_ra['ok'] ), $php_ra );
$ma_moi = isset( $php_ra['head']['id'] ) ? $php_ra['head']['id'] : '';
t( 'mã báo cáo đúng khuôn RP<ngày>-<số>', 1 === preg_match( '/^RP\d{8}-\d{4,}$/', $ma_moi ), $ma_moi );

/* Bảo mã gốc dùng LẠI ĐÚNG MÃ ẤY — xem chú thích `__maMoi` ở `jp-doc-goc.js`. */
$SO_G = $SO;
$SO_G['__maMoi'] = $ma_moi;
$g = goc( 'mo_bao_cao', array( array( 'the', 'L1', '2026-09-01', '2026-09-15', 'TIEN' ) ),
	$SO_G, $NV, true );
t( '🔴 mã gốc cũng tạo được', is_array( $g ) && isset( $g['ra'] ), $g );

if ( is_array( $g ) && isset( $g['ra'] ) ) {
	$goc_ra = $g['ra'][0];
	/* `anhTienDo` khác CÓ CHỦ Ý (đường ảnh chưa chuyển) — xem `class-vhjp-bao-cao.php`. */
	foreach ( array( 'ok', 'coDhTrung', 'chonGiaXung', 'canEdit' ) as $k ) {
		teq( "mo · $k", isset( $goc_ra[ $k ] ) ? $goc_ra[ $k ] : null,
			isset( $php_ra[ $k ] ) ? $php_ra[ $k ] : null );
	}
	chieu( 'mo · head', $goc_ra['head'], $php_ra['head'] );
	chieu( 'mo · gieo', $goc_ra['gieo'], $php_ra['gieo'] );

	teq( 'mo · số khu vực', count( $goc_ra['zones'] ), count( $php_ra['zones'] ) );
	foreach ( $goc_ra['zones'] as $i => $z ) { chieu( "mo · zones[$i]", $z, $php_ra['zones'][ $i ] ); }
	teq( 'mo · số dòng gieo', count( $goc_ra['rows'] ), count( $php_ra['rows'] ) );
	foreach ( $goc_ra['rows'] as $i => $r ) { chieu( "mo · rows[$i]", $r, $php_ra['rows'][ $i ] ); }
}

// ============================================================ 3. 🔴 GIEO CÁI GÌ, BỎ CÁI GÌ
$ma_dong = array_column( $php_ra['rows'], 'itemCode' );
t( '🔴 dòng HÀNG đã trả kho về 0 thì BỎ', ! in_array( 'H-TRAKHO', $ma_dong, true ), $ma_dong );
t( '🔴 dòng HÀNG BÁN HẾT (không trả kho) vẫn GIEO — bán hết là chuyện thường',
	in_array( 'H-BANHET', $ma_dong, true ), $ma_dong );
t( '🔴 Ô MÁY tồn 0 + có trả kho vẫn GIEO — bỏ nó là mất luôn cái máy',
	in_array( '150JP113', $ma_dong, true ), $ma_dong );
teq( 'và nói ra đã bỏ mã nào', array( 'H-TRAKHO' ), $php_ra['gieo']['boQuaTraKho'] );

$theo_ma = array();
foreach ( $php_ra['rows'] as $r ) { $theo_ma[ $r['itemCode'] ] = $r; }

/* Đầu kỳ = cuối kỳ trước. */
teq( '🔴 chỉ số ĐẦU kỳ = chỉ số SAU của kỳ trước', 1360, $theo_ma['100JP111']['mBefore'] );
teq( '🔴 đồng hồ trứng đầu kỳ = số sau kỳ trước', 54, $theo_ma['100JP111']['hBefore'] );
teq( '🔴 tồn đầu của mã KHÔNG AI ĐẾM lấy số WEB TÍNH (33), không lấy ô đếm trống (0)',
	33, $theo_ma['H-KHONGDEM']['stockOpen'] );

/* 🔴 Phát sinh trong kỳ phải TRỐNG hoặc 0 — đây là vế dễ mất tiền im lặng nhất. */
foreach ( array( 'mAfter', 'hAfter', 'stockActual' ) as $c ) {
	teq( "🔴 $c của kỳ mới phải TRỐNG (chưa ai gõ)", '', $theo_ma['100JP111'][ $c ] );
}
teq( '🔴 ĐÃ BÁN không mang sang', 0, $theo_ma['100JP111']['soldQty'] );
teq( '🔴 HOÀN KHÁCH không mang sang — mang là trừ tiền thật mà không ai gõ số đó',
	0, $theo_ma['100JP111']['refundAmt'] );
teq( 'ghi chú hoàn khách cũng không mang sang', '', $theo_ma['100JP111']['refundRowNote'] );
teq( '🔴 TRẢ KHO không mang sang', 0, $theo_ma['100JP111']['returnQty'] );
teq( 'ghi chú nạp thêm không mang sang', '', $theo_ma['100JP111']['topupNote'] );
teq( 'ghi chú dòng không mang sang', '', $theo_ma['100JP111']['note'] );
teq( '🔴 tiền của kỳ trước không mang sang', 0, $theo_ma['100JP111']['bank'] );

/* Mang sang thì phải mang ĐỦ. */
teq( 'mã MISA mang sang', 'MS111', $theo_ma['100JP111']['itemMisa'] );
teq( 'giá mang sang', 20000, $theo_ma['100JP111']['price'] );
teq( '🔴 GIÁ 1 XUNG mang sang theo DÒNG — bắt chọn lại mỗi kỳ là chỗ họ sẽ quên',
	10000, $theo_ma['100JP111']['giaXung'] );
teq( 'dòng chưa chọn giá xung thì rơi về mặc định', 5000, $theo_ma['150JP113']['giaXung'] );

/* Khu vực: tên trống thì đặt tên, ghi chú KHÔNG mang sang. */
teq( 'khu vực gieo đủ', 2, count( $php_ra['zones'] ) );
teq( 'khu vực trống tên thì được đặt tên', 'Khu vực 2', $php_ra['zones'][1]['name'] );
teq( 'ghi chú khu vực không mang sang', '', $php_ra['zones'][0]['note'] );
teq( 'cụm máy thì mang sang', 'C1', $php_ra['zones'][0]['clusterId'] );

/* Dòng trỏ về khu vực MỚI, không trỏ về khu vực của báo cáo cũ. */
$ma_khu = array_column( $php_ra['zones'], 'id' );
foreach ( $php_ra['rows'] as $r ) {
	t( '🔴 dòng trỏ về khu vực của CHÍNH báo cáo mới (' . $r['itemCode'] . ')',
		in_array( $r['zoneId'], $ma_khu, true ), $r['zoneId'] . ' không thuộc ' . implode( ',', $ma_khu ) );
}

/* Nguồn gieo phải NÓI RA. */
teq( 'nói rõ gieo từ báo cáo nào', 'RP20260801-0001', $php_ra['gieo']['tuBaoCao'] );
teq( 'và tới ngày nào', '2026-08-31', $php_ra['gieo']['denNgay'] );
teq( 'và nguồn ĐÃ DUYỆT hay chưa', true, $php_ra['gieo']['daDuyet'] );
teq( 'số dòng đã gieo', 4, $php_ra['gieo']['soDong'] );

// ============================================================ 4. MỞ LẠI THÌ KHÔNG TẠO THÊM
$lan2 = VHJP_BaoCao::mo( $NV, 'L1', '2026-09-01', '2026-09-15', 'TIEN' );
teq( '🔴 mở lại cùng kỳ ⇒ TRẢ VỀ ĐÚNG báo cáo cũ, không đẻ bản thứ hai',
	$ma_moi, $lan2['head']['id'] );
teq( 'và sổ vẫn chỉ có 2 báo cáo', 2, count( VHJP_Nguon::doc( 'JP_Reports' ) ) );
teq( 'không gieo thêm dòng nào nữa', 4, count( $lan2['rows'] ) );
t( 'lượt mở lại KHÔNG kèm bản tin gieo', ! isset( $lan2['gieo'] ), $lan2['gieo'] ?? null );

/* Đã HOÀN TẤT thì khoá. */
VHJP_Nguon::sua( 'JP_Reports', $ma_moi, array( 'status' => 'HOAN_TAT' ) );
$khoa = VHJP_BaoCao::mo( $NV, 'L1', '2026-09-01', '2026-09-15', 'TIEN' );
teq( '🔴 kỳ đã duyệt xong ⇒ không mở để sửa', false, $khoa['ok'] );
teq( 'và nói rõ là bị khoá', true, $khoa['locked'] );
t( 'câu báo nêu đúng kỳ theo lối ngày/tháng/năm',
	false !== mb_strpos( $khoa['msg'], '01/09/2026' ), $khoa['msg'] );
$g = goc( 'mo_bao_cao', array( array( 'the', 'L1', '2026-09-01', '2026-09-15', 'TIEN' ) ),
	array_merge( $SO_G, array( 'JP_Reports' => array_merge( $SO['JP_Reports'], array(
		array( 'id' => $ma_moi, 'locationId' => 'L1', 'machineType' => 'TIEN', 'bcMau' => '',
			'fromDate' => '2026-09-01', 'toDate' => '2026-09-15', 'userId' => 'U1',
			'status' => 'HOAN_TAT' ) ) ) ) ), $NV );
chieu( '🔴 câu khoá y hệt mã gốc', isset( $g[0] ) ? $g[0] : $g, $khoa );
VHJP_Nguon::sua( 'JP_Reports', $ma_moi, array( 'status' => 'NHAP' ) );

// ============================================================ 5. BA CÂU "VÌ SAO KHÔNG GIEO"
/* ① Cơ sở chưa từng có kỳ nào. */
$dau = VHJP_BaoCao::mo( $NV, 'L2', '2026-09-01', '2026-09-15', 'TIEN' );
teq( '① kỳ ĐẦU TIÊN của cơ sở', 'KY_DAU', $dau['gieo']['ma'] );
teq( '① không gieo dòng nào', 0, $dau['gieo']['soDong'] );
t( '① và nói rõ lần này nhập tay', false !== mb_strpos( $dau['gieo']['lyDo'], 'nhập tay' ),
	$dau['gieo']['lyDo'] );

/* ② Có kỳ cũ nhưng CHỒNG NGÀY — tình huống DUY NHẤT người dùng tự sửa được. */
$chong = VHJP_BaoCao::mo( $NV, 'L1', '2026-08-15', '2026-08-31', 'TIEN' );
teq( '② hai kỳ CHỒNG NHAU', 'CHONG_KY', $chong['gieo']['ma'] );
t( '② câu trả lời CHỈ THẲNG vào nút Đổi kỳ',
	false !== mb_strpos( $chong['gieo']['lyDo'], 'Đổi kỳ' ), $chong['gieo']['lyDo'] );
/* ⚠️ Báo cáo "đang chắn" là cái GẦN NHẤT của cơ sở, và tới đây sổ đã có thêm báo cáo vừa tạo
   ở phép ②. Nên đòi đích danh một mã chép cứng là đòi sai — hỏi lại chính `ky_lien_truoc()`
   xem cái gần nhất là cái nào, rồi đòi câu trả lời NÊU ĐÚNG NÓ. */
$ky_l1 = VHJP_BaoCao::ky_lien_truoc( 'L1', '2026-08-15', '', 'TIEN', VHJP_CauHinh::MAU_CHUNG );
$gan_nhat = $ky_l1['cungCoSo'];
usort( $gan_nhat, function ( $a, $b ) {
	return VHJP_Doc::ngay( $a['toDate'] ) < VHJP_Doc::ngay( $b['toDate'] ) ? 1 : -1;
} );
t( '② và nêu đích danh báo cáo đang chắn (cái GẦN NHẤT)',
	false !== mb_strpos( $chong['gieo']['lyDo'], (string) $gan_nhat[0]['id'] ),
	$chong['gieo']['lyDo'] );

/* ③ Kỳ trước còn là NHÁP — chưa nộp lần nào. */
VHJP_Nguon::them( 'JP_Reports', array( 'id' => 'RP20260701-0009', 'locationId' => 'L3',
	'machineType' => 'XU', 'bcMau' => '', 'fromDate' => '2026-07-01', 'toDate' => '2026-07-31',
	'userId' => 'U1', 'userName' => 'Nam', 'status' => 'NHAP' ) );
$nhap = VHJP_BaoCao::mo( $NV, 'L3', '2026-08-01', '2026-08-31', 'XU' );
teq( '③ kỳ trước còn NHÁP', 'CHUA_NOP', $nhap['gieo']['ma'] );
t( '③ và nói rõ phải nộp kỳ đó trước',
	false !== mb_strpos( $nhap['gieo']['lyDo'], 'Nộp kỳ đó' ), $nhap['gieo']['lyDo'] );

/* ④ Kỳ trước đã nộp nhưng KHÔNG CÓ DÒNG NÀO. */
VHJP_Nguon::sua( 'JP_Reports', 'RP20260701-0009', array( 'status' => 'CHO_DUYET' ) );
$rong = VHJP_BaoCao::mo( $NV, 'L3', '2026-09-01', '2026-09-30', 'XU' );
teq( '④ kỳ trước RỖNG', 'KY_TRUOC_RONG', $rong['gieo']['ma'] );
teq( '④ vẫn nói ra nguồn', 'RP20260701-0009', $rong['gieo']['tuBaoCao'] );

/*
 * Đối chiếu với mã gốc ở TẦNG HÀM THUẦN, không gọi lại `mo()`.
 *
 * ⚠️ Gọi `mo()` lần nữa cho cùng một kỳ thì nó TRẢ VỀ BÁO CÁO ĐÃ CÓ (đúng bất biến "1 người ·
 *    1 cơ sở · 1 kỳ ⇒ 1 báo cáo") nên không còn `gieo` để mà so — và mỗi lượt gọi lại đổi
 *    trạng thái sổ, khiến bên node không bao giờ đứng cùng một điểm xuất phát. Hai hàm dưới
 *    đây THUẦN: đưa gì vào ra nấy, nên đối chiếu được sạch sẽ.
 */
$head_thu = array( 'id' => 'RP-THU', 'locationId' => 'L1', 'machineType' => 'TIEN', 'bcMau' => '',
	'fromDate' => '2026-09-01', 'toDate' => '2026-09-15' );
$ds_cu = VHJP_Nguon::tim( 'JP_Reports', 'locationId', 'L1' );
$ung_cu = array();
foreach ( $ds_cu as $r ) { if ( VHJP_Doc::ngay( $r['toDate'] ) < '2026-09-01' ) { $ung_cu[] = $r; } }

foreach ( array(
	array( 'KY_DAU',   array(), array() ),
	array( 'CHONG_KY', $ds_cu, array() ),
	array( 'CHUA_NOP', $ds_cu, $ung_cu ),
) as $ca ) {
	$g = goc( 'vi_sao_khong_gieo',
		array( array( $head_thu, '2026-09-01', $ca[1], $ca[2] ) ), $SO );
	chieu( 'vì sao không gieo · ' . $ca[0], isset( $g[0] ) ? $g[0] : $g,
		VHJP_BaoCao::vi_sao_khong_gieo( $head_thu, '2026-09-01', $ca[1], $ca[2] ) );
}

/* Và `ky_lien_truoc()` — nguồn DUY NHẤT của câu "số đầu kỳ lấy từ đâu". */
$SO_4 = $SO;
$SO_4['JP_Reports'] = array_merge( $SO['JP_Reports'], array(
	/* Một bản DUYỆT TỪ LÂU và một bản VỪA NỘP: bậc thang trạng thái không được thắng ngày. */
	array( 'id' => 'RP20260903-0011', 'locationId' => 'L1', 'machineType' => 'TIEN', 'bcMau' => '',
		'fromDate' => '2026-09-01', 'toDate' => '2026-09-03', 'userId' => 'U1',
		'status' => 'CHO_DUYET' ),
	/* Cùng ngày kết thúc với bản trên nhưng ĐÃ DUYỆT — đây mới là chỗ bậc thang được dùng. */
	array( 'id' => 'RP20260903-0012', 'locationId' => 'L1', 'machineType' => 'TIEN', 'bcMau' => '',
		'fromDate' => '2026-09-01', 'toDate' => '2026-09-03', 'userId' => 'U2',
		'status' => 'HOAN_TAT' ),
	/* Nháp — KHÔNG bao giờ được chọn. */
	array( 'id' => 'RP20260905-0013', 'locationId' => 'L1', 'machineType' => 'TIEN', 'bcMau' => '',
		'fromDate' => '2026-09-04', 'toDate' => '2026-09-05', 'userId' => 'U1',
		'status' => 'NHAP' ),
	/* 🔴 GẦN NHẤT nhưng MỚI NỘP (chưa duyệt). Nó PHẢI thắng bản đã duyệt từ 09-03 — đây là
	   ca duy nhất phân biệt được "ngày gần nhất thắng" với "bậc thang trạng thái thắng",
	   và bản đầu của mã gốc từng làm sai đúng ở đây. */
	array( 'id' => 'RP20260907-0014', 'locationId' => 'L1', 'machineType' => 'TIEN', 'bcMau' => '',
		'fromDate' => '2026-09-06', 'toDate' => '2026-09-07', 'userId' => 'U1',
		'status' => 'CHO_DUYET' ),
	/* 🔴 KẾT THÚC ĐÚNG NGÀY kỳ mới bắt đầu — mốc để đo `toDate < f` chứ không phải `<=`. */
	array( 'id' => 'RP20260908-0015', 'locationId' => 'L1', 'machineType' => 'TIEN', 'bcMau' => '',
		'fromDate' => '2026-09-08', 'toDate' => '2026-09-08', 'userId' => 'U1',
		'status' => 'HOAN_TAT' ),
) );
foreach ( $SO_4['JP_Reports'] as $r ) {
	if ( in_array( $r['id'], array( 'RP20260903-0011', 'RP20260903-0012', 'RP20260905-0013',
		'RP20260907-0014', 'RP20260908-0015' ), true ) ) {
		VHJP_Nguon::them( 'JP_Reports', $r );
	}
}
foreach ( array(
	array( 'L1', '2026-09-10', '', 'TIEN', '' ),
	array( 'L1', '2026-09-08', '', 'TIEN', '' ),
	array( 'L1', '2026-08-01', '', 'TIEN', '' ),
	array( 'L1', '2026-09-10', '', 'XU', '' ),
	array( 'L1', '2026-09-10', '', 'TIEN', 'TACH' ),
	array( 'L9', '2026-09-10', '', 'TIEN', '' ),
) as $ca ) {
	$g = goc( 'ky_lien_truoc', array( array( $ca[0], $ca[1], $ca[2], $ca[3], $ca[4] ) ), so_tu_csdl() );
	$p = VHJP_BaoCao::ky_lien_truoc( $ca[0], $ca[1], $ca[2], $ca[3], $ca[4] );
	$nhan = 'ky_lien_truoc(' . implode( ',', $ca ) . ')';
	$gg = isset( $g[0] ) ? $g[0] : array();
	teq( "$nhan · kỳ trước là cái nào",
		isset( $gg['truoc']['id'] ) ? (string) $gg['truoc']['id'] : null,
		isset( $p['truoc']['id'] ) ? (string) $p['truoc']['id'] : null );
	teq( "$nhan · số báo cáo cùng cơ sở", count( isset( $gg['cungCoSo'] ) ? $gg['cungCoSo'] : array() ),
		count( $p['cungCoSo'] ) );
	teq( "$nhan · số báo cáo ứng cử", count( isset( $gg['ung'] ) ? $gg['ung'] : array() ),
		count( $p['ung'] ) );
}
$ky = VHJP_BaoCao::ky_lien_truoc( 'L1', '2026-09-10', '', 'TIEN', '' );
teq( '🔴 kỳ GẦN NHẤT thắng, dù nó MỚI NỘP còn bản kia ĐÃ DUYỆT', 'RP20260908-0015',
	(string) $ky['truoc']['id'] );
teq( '🔴 bản NHÁP không bao giờ được chọn làm khuôn', false,
	'RP20260905-0013' === (string) $ky['truoc']['id'] );

/* 🔴 Bỏ mốc 09-08 ra thì bản MỚI NỘP 09-07 phải thắng bản ĐÃ DUYỆT 09-03. Đây là phép đo
   riêng cho "ngày thắng bậc thang" — bản đầu của mã gốc làm ngược và tồn đầu nhảy qua cả
   một kỳ phát sinh. */
VHJP_Nguon::xoa( 'JP_Reports', 'RP20260908-0015' );
$ky_b = VHJP_BaoCao::ky_lien_truoc( 'L1', '2026-09-10', '', 'TIEN', '' );
teq( '🔴 MỚI NỘP gần hơn thắng ĐÃ DUYỆT xa hơn', 'RP20260907-0014',
	(string) $ky_b['truoc']['id'] );

/* 🔴 `toDate < f`, KHÔNG phải `<=`: kỳ kết thúc ĐÚNG ngày kỳ mới bắt đầu thì KHÔNG được
   dùng làm khuôn — tồn cuối của một ngày không thể là tồn đầu của chính ngày đó. */
VHJP_Nguon::them( 'JP_Reports', array( 'id' => 'RP20260908-0015', 'locationId' => 'L1',
	'machineType' => 'TIEN', 'bcMau' => '', 'fromDate' => '2026-09-08', 'toDate' => '2026-09-08',
	'userId' => 'U1', 'status' => 'HOAN_TAT' ) );
$ky_c = VHJP_BaoCao::ky_lien_truoc( 'L1', '2026-09-08', '', 'TIEN', '' );
$ma_ung = array_map( 'strval', array_column( $ky_c['ung'], 'id' ) );
t( '🔴 kỳ kết thúc ĐÚNG ngày kỳ mới bắt đầu KHÔNG được vào danh sách ứng cử',
	! in_array( 'RP20260908-0015', $ma_ung, true ), $ma_ung );
t( 'nhưng nó VẪN nằm trong danh sách "cùng cơ sở" — hai danh sách khác nhau, và câu trả lời '
	. '"vì sao không gieo" dựa vào chính chỗ khác nhau ấy',
	in_array( 'RP20260908-0015', array_map( 'strval', array_column( $ky_c['cungCoSo'], 'id' ) ), true ) );

$nem = function ( $f ) { try { $f(); return ''; } catch ( Throwable $e ) { return $e->getMessage(); } };

// ============================================================ 5b. 🔴 BÁO CÁO CỦA NGƯỜI KHÁC
/*
 * Cùng cơ sở · cùng kỳ · cùng loại máy nhưng KHÁC NGƯỜI ⇒ phải tạo bản RIÊNG, không được mở
 * bản của người kia ra cho gõ tiếp. Bất biến "báo cáo thuộc về NGƯỜI TẠO" (Andy chốt
 * 03/08/2026) nằm ở đúng phép lọc này; bỏ nó là hai người gõ đè lên nhau cả ca.
 */
$nguoi_kia = array( 'id' => 'U2', 'hoTen' => 'Bình', 'role' => 'NHANVIEN', 'machineType' => '',
	'locationIds' => array( 'L1' ) );
$bc_b = VHJP_BaoCao::mo( $nguoi_kia, 'L1', '2026-11-01', '2026-11-15', 'TIEN' );
$bc_a = VHJP_BaoCao::mo( $NV, 'L1', '2026-11-01', '2026-11-15', 'TIEN' );
t( '🔴 cùng kỳ mà khác người ⇒ HAI báo cáo riêng',
	$bc_a['head']['id'] !== $bc_b['head']['id'],
	$bc_a['head']['id'] . ' vs ' . $bc_b['head']['id'] );
teq( 'và mỗi bản mang đúng tên người tạo', 'Nam', $bc_a['head']['userName'] );
teq( 'còn bản kia vẫn của người kia', 'Bình', $bc_b['head']['userName'] );
t( '🔴 và báo cáo của mình NÓI RA là có người khác cùng kỳ',
	count( $bc_a['trungNguoiKhac'] ) > 0, $bc_a['trungNguoiKhac'] );

// ============================================================ 5c. 🔴 GHI HỎNG PHẢI NÓI RA
/*
 * Cơ sở dữ liệu chối lượt ghi (ổ cứng đầy, bảng hỏng, quyền sai) thì `mo()` phải NÉM, không
 * được trả về như thật. Trả về như thật là nhân viên gõ cả ca vào một báo cáo KHÔNG TỒN TẠI,
 * và chỉ tới lượt lưu sau mới báo lỗi — lúc ấy số đã gõ mất sạch. Bộ Ghế đã mất một giao dịch
 * vì đúng nước đi ngược lại (`VHG_Thu::ghi`, sửa 16/09/2026).
 */
$wpdb->exec_raw( 'CREATE TRIGGER vhjp_chan_bc BEFORE INSERT ON ' . VHJP_Nguon::bang( 'JP_Reports' )
	. " BEGIN SELECT RAISE(ABORT, 'o cung hong'); END" );
$loi_ghi = $nem( function () use ( $NV ) {
	VHJP_BaoCao::mo( $NV, 'L1', '2026-12-01', '2026-12-15', 'TIEN' );
} );
$wpdb->exec_raw( 'DROP TRIGGER vhjp_chan_bc' );
t( '🔴 ghi hỏng thì NÉM, không gật đầu', '' !== $loi_ghi, $loi_ghi );
t( 'và câu báo nói được là chưa ghi được',
	false !== mb_strpos( $loi_ghi, 'Không ghi được' ), $loi_ghi );
teq( 'không để lại báo cáo ma trong sổ', 0,
	count( VHJP_Nguon::tim( 'JP_Reports', 'fromDate', '2026-12-01' ) ) );

// ============================================================ 6. CHỐT ĐẦU VÀO & QUYỀN
teq( 'chưa chọn ngày ⇒ nói rõ', 'Chọn ngày báo cáo',
	$nem( function () use ( $NV ) { VHJP_BaoCao::mo( $NV, 'L1', '', '', 'TIEN' ); } ) );
teq( 'ngày kết thúc trước ngày bắt đầu ⇒ nói rõ', 'Ngày kết thúc phải sau ngày bắt đầu',
	$nem( function () use ( $NV ) { VHJP_BaoCao::mo( $NV, 'L1', '2026-09-10', '2026-09-01', 'TIEN' ); } ) );
/* ⚠️ Chốt QUYỀN đứng TRƯỚC phép tra danh mục — y bản gốc (`jpNeedLoc_` rồi mới `jpFindOne_`).
   Nên muốn chạm tới câu "Không tìm thấy cơ sở" thì phải là người ĐƯỢC GÁN đúng mã ấy. Thứ tự
   này là đúng: người ngoài không được biết mã nào có thật, mã nào không. */
$co_l404 = array( 'id' => 'U7', 'hoTen' => 'Bảy', 'role' => 'NHANVIEN', 'machineType' => '',
	'locationIds' => array( 'L404' ) );
teq( 'cơ sở không có thật ⇒ nói rõ', 'Không tìm thấy cơ sở',
	$nem( function () use ( $co_l404 ) { VHJP_BaoCao::mo( $co_l404, 'L404', '2026-09-01', '2026-09-15', 'TIEN' ); } ) );
teq( '🔴 người KHÔNG được gán thì chỉ nhận câu chặn quyền, không biết mã có thật hay không',
	'Không có quyền với cơ sở này',
	$nem( function () use ( $NV ) { VHJP_BaoCao::mo( $NV, 'L404', '2026-09-01', '2026-09-15', 'TIEN' ); } ) );

$la = array( 'id' => 'U8', 'hoTen' => 'Lạ', 'role' => 'NHANVIEN', 'machineType' => '',
	'locationIds' => array( 'L2' ) );
teq( '🔴 cơ sở không được gán ⇒ CHẶN', 'Không có quyền với cơ sở này',
	$nem( function () use ( $la ) { VHJP_BaoCao::mo( $la, 'L1', '2026-09-01', '2026-09-15', 'TIEN' ); } ) );

$chan = $nem( function () use ( $NV_XU ) {
	VHJP_BaoCao::mo( $NV_XU, 'L1', '2026-10-01', '2026-10-15', 'TIEN' );
} );
t( '🔴 tài khoản gán MÁY XU không mở được báo cáo MÁY TIỀN',
	false !== mb_strpos( $chan, 'MÁY XU' ) && false !== mb_strpos( $chan, 'MÁY TIỀN' ), $chan );
$g = goc( 'mo_bao_cao', array( array( 'the', 'L1', '2026-10-01', '2026-10-15', 'TIEN' ) ),
	$SO, $NV_XU );
t( '🔴 và mã gốc chặn bằng ĐÚNG câu ấy',
	isset( $g['loi'] ) && false !== mb_strpos( $g['loi'], $chan ), array( $g, $chan ) );
teq( 'gán rỗng thì mở được cả hai loại', '', VHJP_Auth::may_type_cua( $NV ) );
teq( 'kế toán không bị chặn loại máy', '', VHJP_Auth::may_type_cua( $KT ) );

/* Loại máy CHỐT LÚC TẠO, và mặc định lấy theo người dùng chứ không theo cơ sở. */
$xu = VHJP_BaoCao::mo( $NV_XU, 'L1', '2026-10-01', '2026-10-15', '' );
teq( '🔴 không nói loại máy thì lấy loại ĐƯỢC GÁN của người, không lấy của cơ sở',
	'XU', $xu['head']['machineType'] );
/* 🔴 Đo ở CƠ SỞ KHAI MẪU TÁCH, không đo ở cơ sở mẫu chung: ở đó hai luật cho ra cùng một kết
   quả nên phép đo mù. Mẫu TÁCH chỉ có ở báo cáo máy TIỀN — máy xu vốn đã tách hai bảng rồi. */
$xu4 = VHJP_BaoCao::mo( $NV_XU, 'L4', '2026-10-01', '2026-10-15', 'XU' );
teq( '🔴 máy XU ở cơ sở khai mẫu TÁCH vẫn dùng mẫu CHUNG',
	VHJP_CauHinh::MAU_CHUNG, $xu4['head']['bcMau'] );
$tien4 = VHJP_BaoCao::mo( $NV, 'L4', '2026-10-01', '2026-10-15', 'TIEN' );
teq( '🔴 còn máy TIỀN ở chính cơ sở ấy thì theo mẫu TÁCH của cơ sở',
	VHJP_CauHinh::MAU_TACH, $tien4['head']['bcMau'] );
teq( 'máy XU ở cơ sở mẫu chung thì cũng là mẫu chung',
	VHJP_CauHinh::MAU_CHUNG, $xu['head']['bcMau'] );

// ============================================================ 7. 🔴 Ô TRỐNG XUỐNG SỔ LÀ NULL
/*
 * Đây là phép đo THẲNG VÀO CƠ SỞ DỮ LIỆU, không qua lớp đóng gói: `pub_dong()` biến cả `NULL`
 * lẫn `''` thành `''` nên nó KHÔNG phân biệt được hai thứ. Mà khác biệt ấy lại là sống còn:
 * `''` vào cột `DECIMAL NULL` thì MySQL chặt CHỐI CẢ DÒNG, MySQL lỏng lặng lẽ đổi thành `0`.
 */
global $wpdb;
$b = VHJP_DB::t( 'dong' );
$hang = $wpdb->get_row( $wpdb->prepare(
	'SELECT mAfter, stockActual, soldQty, note FROM ' . $b . ' WHERE reportId = %s AND itemCode = %s',
	$ma_moi, '100JP111' ), ARRAY_A );
t( '🔴 đọc lại được dòng vừa gieo từ CHÍNH cơ sở dữ liệu', is_array( $hang ), $hang );
if ( is_array( $hang ) ) {
	foreach ( array( 'mAfter', 'stockActual' ) as $c ) {
		teq( "🔴 cột số chưa ai gõ ($c) xuống sổ là NULL, không phải '' hay 0",
			null, $hang[ $c ] );
	}
	/* ⚠️ `soldQty` thì KHÁC: nó là số MÁY TÍNH RA, không phải ô người gõ. Lúc gieo, máy chủ
	   chạy phép tính dòng trên một dòng chưa có chỉ số sau nên ra 0 — và mã gốc cũng vậy
	   (phép đối chiếu ở mục 2 đã đòi hai bên bằng nhau). Ghi rõ ở đây để lần sau không ai
	   "sửa" nó thành NULL cho đều. */
	teq( 'còn soldQty là số MÁY TÍNH ⇒ 0 mới đúng', '0', (string) $hang['soldQty'] );
	teq( "🔴 cột CHỮ thì ngược lại — '' mới đúng, NULL là chối cả dòng",
		'', $hang['note'] );
}
/* 🔴 Dòng NGOAI không có đồng hồ trứng, nên `hBefore` được gieo từ một ô TRỐNG — tức nó đi
   xuống sổ dưới dạng chuỗi rỗng nếu lớp nguồn không đổi sang NULL. Đây là ô DUY NHẤT trong
   sổ thử này đi qua đúng đường ấy, nên thiếu phép đo này là cả cái luật không được đo. */
$hang_ngoai = $wpdb->get_row( $wpdb->prepare(
	'SELECT hBefore, warnJson, itemName FROM ' . $b . ' WHERE reportId = %s AND itemCode = %s',
	$ma_moi, 'H-BANHET' ), ARRAY_A );
t( 'đọc lại được dòng hàng vừa gieo', is_array( $hang_ngoai ), $hang_ngoai );
if ( is_array( $hang_ngoai ) ) {
	teq( "🔴 ô trống gieo xuống cột số phải thành NULL, không phải ''",
		null, $hang_ngoai['hBefore'] );
	teq( 'cột chữ nhận NULL vẫn giữ chuỗi rỗng, không bị đổi', '', (string) $hang_ngoai['warnJson'] );
}

teq( 'lớp nguồn khai đúng mAfter là cột số nhận NULL', true,
	in_array( 'mAfter', VHJP_Nguon::cot_so_nhan_null( 'JP_Rows' ), true ) );
teq( '🔴 cột CHỮ nhận NULL cũng KHÔNG khai — ở đó "" và NULL là hai giá trị khác nhau, '
	. 'và mã đọc ra đang dựa vào ""', false,
	in_array( 'warnJson', VHJP_Nguon::cot_so_nhan_null( 'JP_Rows' ), true ) );
teq( 'xuDaysJson cũng vậy', false,
	in_array( 'xuDaysJson', VHJP_Nguon::cot_so_nhan_null( 'JP_Rows' ), true ) );
teq( '🔴 và KHÔNG khai nhầm cột chữ', false,
	in_array( 'note', VHJP_Nguon::cot_so_nhan_null( 'JP_Rows' ), true ) );
teq( '🔴 cột số NOT NULL cũng không khai (ở đó 0 mới đúng)', false,
	in_array( 'seq', VHJP_Nguon::cot_so_nhan_null( 'JP_Rows' ), true ) );

// ============================================================ 8. NHẬT KÝ & CỔNG
$nk = VHJP_Nguon::tim( 'JP_Audit', 'action', 'REPORT_CREATE' );
t( '🔴 tạo báo cáo có ghi nhật ký', count( $nk ) > 0, count( $nk ) );
$co = false;
foreach ( $nk as $x ) { if ( (string) $x['reportId'] === $ma_moi ) { $co = true; } }
t( 'và ghi đúng mã báo cáo', $co, array_column( $nk, 'reportId' ) );

require_once $plg . 'class-vhjp-cong.php';
t( 'cổng khai jpOpenReport', isset( VHJP_Cong::map()['jpOpenReport'] ) );
t( 'jpOpenReport không còn ở bảng chưa làm',
	! in_array( 'jpOpenReport', VHJP_Cong::chua_lam(), true ) );
t( '🔴 jpOpenReport là màn của NHÂN VIÊN — phải có trong bảng gác vai',
	in_array( 'jpOpenReport', VHJP_Cong::chi_nhan_vien(), true ), VHJP_Cong::chi_nhan_vien() );

// ============================================================ kết
if ( $truot ) {
	echo "HỎNG: " . count( $truot ) . "\n";
	foreach ( $truot as $x ) { echo "  ✗ $x\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — tạo báo cáo và gieo dòng khớp mã gốc chạy thật.\n";
