<?php
/**
 * LƯU BÁO CÁO JP — `VHJP_BaoCao::luu()`
 * =============================================================================================
 *
 * 🔴 ĐÂY LÀ CỬA DUY NHẤT MÀ SỐ LIỆU CỦA NGƯỜI DÙNG ĐI VÀO SỔ. Ba luật sống còn:
 *
 *   ①  GIAO DIỆN KHÔNG GỬI TIỀN LÊN. Mọi con số tiền do máy chủ tính lại từ chỉ số đồng hồ và
 *      số lượng. Nhận tiền từ trình duyệt là nhận một con số ai cũng sửa được bằng công cụ
 *      dành cho người phát triển, và không có cách nào biết nó đã bị sửa.
 *
 *   ②  Ô TRỐNG PHẢI Ở LẠI LÀ Ô TRỐNG. `cash` · `amount` · `cashReal` · chỉ số đồng hồ · ô đếm
 *      tồn đi qua `num_hoac_trong()`. Dùng `num()` là chúng thành 0 ngay tại cửa vào, phép
 *      chặn nộp đọc lại chỉ thấy 0 nên KHÔNG chặn, và lệch tiền mặt ra −(tiền mặt app) ⇒ tiền
 *      phải nộp của cả kỳ về 0. Sổ vẫn cân.
 *
 *   ③  MÃ HÀNG SNAP VỀ CÁCH VIẾT CHUẨN. Nhân viên gõ `100jp031` chữ thường là mã hoàn toàn
 *      đúng; không snap thì duyệt xong kho không tìm được lớp tồn ⇒ giá vốn về 0đ, SỔ 632
 *      THIẾU trong khi sổ vẫn CÂN.
 *
 * Bài này nạp cùng một bộ dữ liệu vào hai nơi rồi cho CẢ HAI cùng lưu một payload, sau đó đòi
 * hai bên ra y hệt — cả giá trị trả về lẫn thứ đã ghi xuống sổ.
 *
 * Chạy: php tools/test/kiem-jp-luu-bao-cao.php
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
	'JP_Items' => array(
		array( 'code' => '100JP031', 'misa' => 'MS031', 'name' => 'Trứng 100k', 'price' => 0,
			'dvt' => '', 'active' => 'Y' ),
		array( 'code' => '20JP007', 'misa' => 'MS007', 'name' => 'Trứng 20k', 'price' => 0,
			'dvt' => 'Cái', 'active' => 'Y' ),
		array( 'code' => 'A-077', 'misa' => 'MSA77', 'name' => 'Mã nội bộ', 'price' => 45000,
			'dvt' => '', 'active' => 'Y' ),
		/* 🔴 HAI MÃ CHỈ KHÁC CHỮ HOA. Khoá viết HOA lúc ấy trỏ vào đâu cũng là ĐOÁN, nên
		   bảng tra phải BỎ HẲN khoá đó — thà không tìm thấy còn hơn gán giá vốn của mặt
		   hàng này sang mặt hàng khác, mà sổ vẫn cân nên không phép kiểm nào bắt được. */
		array( 'code' => 'ab1', 'misa' => 'MSAB1', 'name' => 'Nhập nhằng thường', 'price' => 1000,
			'dvt' => '', 'active' => 'Y' ),
		array( 'code' => 'Ab1', 'misa' => 'MSAB2', 'name' => 'Nhập nhằng hoa', 'price' => 2000,
			'dvt' => '', 'active' => 'Y' ),
	),
	'JP_Locations' => array(
		array( 'id' => 'L1', 'code' => 'AMBD', 'name' => 'AEON Bình Dương', 'maKH' => 'KH00119',
			'machineType' => 'TIEN', 'bcMau' => '', 'coDhTrung' => '', 'chonGiaXung' => 'Y',
			'active' => 'Y' ),
		/* Cơ sở KHÔNG bật cờ chọn giá xung — dòng nào gửi giá lạ lên cũng phải bị ÉP về mặc định. */
		array( 'id' => 'L2', 'code' => 'KHOA', 'name' => 'Cơ sở khoá giá xung', 'maKH' => 'KH00200',
			'machineType' => 'TIEN', 'bcMau' => '', 'coDhTrung' => '', 'chonGiaXung' => '',
			'active' => 'Y' ),
		/* Mẫu TÁCH — nơi ba ô tiền là ô NHÂN VIÊN GÕ. */
		array( 'id' => 'L3', 'code' => 'SBPQ', 'name' => 'Sân bay Phú Quốc', 'maKH' => 'KH00300',
			'machineType' => 'TIEN', 'bcMau' => 'TACH', 'coDhTrung' => '', 'chonGiaXung' => '',
			'active' => 'Y' ),
	),
	'JP_Reports' => array(
		array( 'id' => 'RP-A', 'locationId' => 'L1', 'locationName' => 'AEON Bình Dương',
			'maKH' => 'KH00119', 'machineType' => 'TIEN', 'bcMau' => '',
			'fromDate' => '2026-09-01', 'toDate' => '2026-09-15', 'userId' => 'U1',
			'userName' => 'Nam', 'status' => 'NHAP', 'revMeter' => 0, 'warnCount' => 0,
			'adjMachine' => 0, 'refundCustomer' => 0, 'refundRows' => 0, 'revBank' => 0 ),
		array( 'id' => 'RP-B', 'locationId' => 'L2', 'locationName' => 'Cơ sở khoá giá xung',
			'machineType' => 'TIEN', 'bcMau' => '', 'fromDate' => '2026-09-01',
			'toDate' => '2026-09-15', 'userId' => 'U1', 'userName' => 'Nam', 'status' => 'NHAP' ),
		array( 'id' => 'RP-C', 'locationId' => 'L3', 'locationName' => 'Sân bay Phú Quốc',
			'machineType' => 'TIEN', 'bcMau' => 'TACH', 'fromDate' => '2026-09-01',
			'toDate' => '2026-09-15', 'userId' => 'U1', 'userName' => 'Nam', 'status' => 'NHAP' ),
		/* Báo cáo ĐÃ HOÀN TẤT, ký GỘP — để đo phép huỷ chữ ký thông minh. */
		array( 'id' => 'RP-D', 'locationId' => 'L1', 'locationName' => 'AEON Bình Dương',
			'machineType' => 'TIEN', 'bcMau' => '', 'fromDate' => '2026-08-01',
			'toDate' => '2026-08-15', 'userId' => 'U1', 'userName' => 'Nam', 'status' => 'CAN_SUA',
			'apprBy' => 'ketoan', 'apprAt' => '2026-08-20 09:00:00',
			'revMeter' => 1000000, 'revBank' => 0, 'adjMachine' => 0, 'refundCustomer' => 0,
			'refundRows' => 0 ),
		/* Báo cáo của NGƯỜI KHÁC — không ai được lưu đè. */
		array( 'id' => 'RP-E', 'locationId' => 'L1', 'machineType' => 'TIEN', 'bcMau' => '',
			'fromDate' => '2026-07-01', 'toDate' => '2026-07-15', 'userId' => 'U2',
			'userName' => 'Bình', 'status' => 'NHAP' ),
		/* Báo cáo ĐÃ NỘP — chính chủ cũng không sửa được. */
		array( 'id' => 'RP-F', 'locationId' => 'L1', 'machineType' => 'TIEN', 'bcMau' => '',
			'fromDate' => '2026-06-01', 'toDate' => '2026-06-15', 'userId' => 'U1',
			'userName' => 'Nam', 'status' => 'CHO_DUYET' ),
		/* 🔴 CỦA CHÍNH MÌNH, nhưng ở cơ sở KHÔNG được gán. Chỉ ca này mới đo được chốt quyền
		   cơ sở — mọi ca khác đã bị chốt "chủ sở hữu" chặn trước rồi. */
		array( 'id' => 'RP-G', 'locationId' => 'L9', 'machineType' => 'TIEN', 'bcMau' => '',
			'fromDate' => '2026-05-01', 'toDate' => '2026-05-15', 'userId' => 'U1',
			'userName' => 'Nam', 'status' => 'NHAP' ),
		/* 🔴 HAI CHỮ KÝ RIÊNG (không phải chữ ký gộp). Chỉ ca này mới đo được "sửa phần nào
		   huỷ phần đó" — ở báo cáo ký gộp, nhánh cấp lại chữ ký che mất phép đo. */
		array( 'id' => 'RP-H', 'locationId' => 'L1', 'locationName' => 'AEON Bình Dương',
			'machineType' => 'TIEN', 'bcMau' => '', 'fromDate' => '2026-04-01',
			'toDate' => '2026-04-15', 'userId' => 'U1', 'userName' => 'Nam', 'status' => 'CAN_SUA',
			'apprRevBy' => 'ketoan', 'apprRevAt' => '2026-04-20 09:00:00',
			'apprStockBy' => 'ketoan', 'apprStockAt' => '2026-04-20 09:05:00',
			'revMeter' => 1000000, 'revBank' => 0, 'adjMachine' => 0, 'refundCustomer' => 0,
			'refundRows' => 0 ),
	),
	'JP_Zones' => array(
		array( 'id' => 'RP-D-Z1', 'reportId' => 'RP-D', 'seq' => 1, 'name' => 'Tầng 1',
			'clusterId' => 'C1', 'note' => '' ),
		array( 'id' => 'RP-H-Z1', 'reportId' => 'RP-H', 'seq' => 1, 'name' => 'Tầng 1',
			'clusterId' => 'C1', 'note' => '' ),
	),
	'JP_Rows' => array(
		array( 'id' => 'RP-D-R1', 'reportId' => 'RP-D', 'zoneId' => 'RP-D-Z1', 'seq' => 1,
			'rowKind' => 'MONEY', 'machineId' => 'M1', 'machineCode' => '100JP031',
			'itemCode' => '100JP031', 'itemMisa' => 'MS031', 'price' => 100000,
			'mBefore' => 100, 'mAfter' => 110, 'mActual' => 10, 'amount' => 1000000,
			'hOpen' => 20, 'hBefore' => 20, 'hAfter' => 10, 'soldQty' => 10,
			'stockActual' => 10, 'stockLeftCalc' => 10, 'bank' => 0 ),
		array( 'id' => 'RP-H-R1', 'reportId' => 'RP-H', 'zoneId' => 'RP-H-Z1', 'seq' => 1,
			'rowKind' => 'MONEY', 'machineId' => 'M1', 'machineCode' => '100JP031',
			'itemCode' => '100JP031', 'itemMisa' => 'MS031', 'price' => 100000,
			'mBefore' => 100, 'mAfter' => 110, 'mActual' => 10, 'amount' => 1000000,
			'hOpen' => 20, 'hBefore' => 20, 'hAfter' => 10, 'soldQty' => 10,
			'stockActual' => 10, 'stockLeftCalc' => 10, 'bank' => 0 ),
	),
);
foreach ( $SO as $tab => $ds ) {
	foreach ( $ds as $hang ) {
		$k = isset( $hang['id'] ) ? $hang['id'] : $hang['code'];
		t( "nạp được $tab#$k", false !== VHJP_Nguon::them( $tab, $hang ), VHJP_Nguon::loi_cuoi() );
	}
}

$NV  = array( 'id' => 'U1', 'hoTen' => 'Nam', 'role' => 'NHANVIEN', 'machineType' => '',
	'locationIds' => array( 'L1', 'L2', 'L3' ) );
$NV2 = array( 'id' => 'U2', 'hoTen' => 'Bình', 'role' => 'NHANVIEN', 'machineType' => '',
	'locationIds' => array( 'L1' ) );

function so_tu_csdl() {
	$ra = array();
	foreach ( array( 'JP_Items', 'JP_Locations', 'JP_Reports', 'JP_Zones', 'JP_Rows' ) as $tab ) {
		$ra[ $tab ] = VHJP_Nguon::doc( $tab );
	}
	return $ra;
}
function goc( $ten, $ca, $so = null, $ai = null, $tra_so = false ) {
	global $cau_noi;
	$ra = array(); $ma = 0;
	$lenh = ( $tra_so ? 'JP_TRA_SO=1 ' : '' )
		. 'node ' . escapeshellarg( $cau_noi ) . ' ' . escapeshellarg( $ten ) . ' '
		. escapeshellarg( wp_json_encode( $ca ) );
	if ( null !== $so ) { $lenh .= ' ' . escapeshellarg( wp_json_encode( $so ) ); }
	if ( null !== $ai ) { $lenh .= ' ' . escapeshellarg( wp_json_encode( $ai ) ); }
	exec( $lenh . ' 2>&1', $ra, $ma );
	if ( 0 !== $ma ) { return array( 'loi' => implode( "\n", $ra ) ); }
	return json_decode( implode( '', $ra ), true );
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

// ============================================================ 2. BẢNG TRA MÃ HÀNG
$g = goc( 'ban_do_hang', array( array() ), $SO );
$bd_goc = isset( $g[0] ) ? $g[0] : array();
$bd_php = VHJP_CauHinh::ban_do_hang();
teq( 'bảng tra mã hàng · đúng bộ khoá',
	( function ( $x ) { $k = array_keys( $x ); sort( $k ); return $k; } )( $bd_goc ),
	( function ( $x ) { $k = array_keys( $x ); sort( $k ); return $k; } )( $bd_php ) );
foreach ( $bd_goc as $k => $v ) {
	chieu( "bảng tra · $k", $v, isset( $bd_php[ $k ] ) ? $bd_php[ $k ] : array() );
}
teq( '🔴 giá suy từ chính mã khi danh mục để trống', 100000, $bd_php['100JP031']['price'] );
teq( 'mã nội bộ không có tiền tố giá thì lấy giá đã khai', 45000, $bd_php['A-077']['price'] );
teq( 'đơn vị tính trống thì rơi về Quả', 'Quả', $bd_php['100JP031']['dvt'] );
teq( 'đã khai thì giữ', 'Cái', $bd_php['20JP007']['dvt'] );

foreach ( array( '100JP031', '100jp031', ' 100JP031 ', 'KHONGCO', '', null ) as $ma ) {
	$g = goc( 'tra_hang', array( array( $bd_goc, $ma ) ), $SO );
	$p = VHJP_CauHinh::tra_hang( $bd_php, $ma );
	teq( 'tra_hang(' . json_encode( $ma ) . ')',
		wp_json_encode( isset( $g[0] ) ? $g[0] : null ), wp_json_encode( $p ) );
}
t( '🔴 gõ chữ thường vẫn tra ra mã chuẩn',
	'100JP031' === VHJP_CauHinh::tra_hang( $bd_php, '100jp031' )['code'] );
teq( '🔴 mã không có trong danh mục trả null, KHÔNG trả mảng rỗng',
	null, VHJP_CauHinh::tra_hang( $bd_php, 'KHONGCO' ) );

/* 🔴 CHỐT CHỐNG NHẬP NHẰNG. `ab1` và `Ab1` cùng viết hoa thành `AB1`, nên khoá `AB1` trỏ vào
   đâu cũng là đoán — phải KHÔNG CÓ khoá ấy. Đoán hộ là gán giá vốn của mặt hàng này sang mặt
   hàng khác, mà sổ vẫn cân. */
t( '🔴 hai mã chỉ khác chữ hoa ⇒ KHÔNG gài khoá viết HOA (không đoán bừa)',
	! isset( $bd_php['AB1'] ), array_keys( $bd_php ) );
teq( 'nên tra theo cách viết HOA thì không thấy gì', null,
	VHJP_CauHinh::tra_hang( $bd_php, 'AB1' ) );
teq( 'nhưng tra ĐÚNG cách viết thì vẫn ra đúng mã', 'MSAB1',
	VHJP_CauHinh::tra_hang( $bd_php, 'ab1' )['misa'] );
teq( 'và cách viết kia cũng vậy', 'MSAB2',
	VHJP_CauHinh::tra_hang( $bd_php, 'Ab1' )['misa'] );

// ============================================================ 3. CẤP MÃ DÒNG KHÔNG TRÙNG
foreach ( array(
	array( array( array( 'id' => 'R1' ), array( 'id' => 'R3' ) ) ),
	array( array( array( 'id' => '' ), array() ) ),
	array( array() ),
) as $ca ) {
	/* ⚠️ So trên MẢNG: `json_decode(..., true)` biến `{}` của mã gốc thành `array()`, nên so
	   bằng JSON hai bên là so `{}` với `[]` — một phép trượt GIẢ. */
	$g = goc( 'ids_dang_dung', array( $ca ), $SO );
	teq( 'ids_dang_dung(' . json_encode( $ca ) . ')',
		isset( $g[0] ) ? $g[0] : null, VHJP_BaoCao::ids_dang_dung( $ca[0] ) );
}
/* 🔴 Ca kinh điển: có -R1 -R2 -R3, xoá -R1, thêm một dòng mới. Dòng mới KHÔNG được mang mã
   -R3 của dòng cũ — trùng mã là ảnh gắn sai dòng và phép so huỷ chữ ký so nhầm dòng. */
$ds_thu = array( array( 'id' => 'X-R2' ), array( 'id' => 'X-R3' ), array( 'id' => '' ) );
$da = VHJP_BaoCao::ids_dang_dung( $ds_thu );
$ra_id = array();
foreach ( $ds_thu as $x ) { $ra_id[] = VHJP_BaoCao::id_moi( $x['id'], 'X-R', $da ); }
teq( '🔴 dòng mới không đè lên mã dòng cũ', array( 'X-R2', 'X-R3', 'X-R1' ), $ra_id );
teq( 'và mọi mã đều khác nhau', 3, count( array_unique( $ra_id ) ) );
$da2 = VHJP_BaoCao::ids_dang_dung( array( array( 'id' => 'X-R1' ) ) );
teq( 'mã cũ còn trống thì giữ nguyên', 'X-R1', VHJP_BaoCao::id_moi( 'X-R1', 'X-R', $da2 ) );
teq( 'gọi lại lần nữa thì phải cấp mã khác', 'X-R2', VHJP_BaoCao::id_moi( 'X-R1', 'X-R', $da2 ) );

// ============================================================ 4. 🔴 LƯU MỘT BÁO CÁO THẬT
/*
 * Payload cố ý gài đủ mìn:
 *  · mã hàng gõ chữ THƯỜNG            -> phải snap về cách viết chuẩn
 *  · mã KHÔNG có trong danh mục       -> phải GIỮ NGUYÊN nguyên văn, không viết hoa lên
 *  · ô chỉ số sau để TRỐNG            -> phải ở lại là ô trống
 *  · `refundAmt` gửi số ÂM            -> phải thành số dương
 *  · gửi kèm `amount`/`cash` bịa đặt  -> máy chủ phải tính lại đè lên
 *  · `refundQty` bịa đặt              -> KHÔNG được nhận từ giao diện
 */
$payload = array(
	'reportId' => 'RP-A',
	'head' => array( 'adjMachine' => -50000, 'adjMachineNote' => 'máy nuốt',
		'refundCustomer' => -20000, 'refundNote' => 'khách trả', 'remark' => 'ổn' ),
	'zones' => array(
		array( 'id' => '', 'name' => '', 'clusterId' => 'C1', 'note' => 'ghi chú khu' ),
		array( 'id' => 'RP-A-Z5', 'name' => 'Tầng 2', 'clusterId' => 'C2', 'note' => '' ),
	),
	'rows' => array(
		array( 'id' => '', 'zoneKey' => 0, 'rowKind' => 'MONEY',
			'machineId' => 'M1', 'machineCode' => 'MAY-01',
			'itemCode' => '100jp031',           // 🔴 chữ thường
			'mBefore' => 100, 'mAfter' => 112, 'hBefore' => 30, 'hAfter' => 20,
			'stockActual' => 20, 'bank' => 200000, 'cashReal' => 900000,
			'refundAmt' => -100000,             // 🔴 số âm
			'refundQty' => 999,                 // 🔴 bịa — không được nhận
			'amount' => 999999999,              // 🔴 bịa — máy chủ tính lại
			'cash' => 888888888,                // 🔴 bịa
			'giaXung' => 10000, 'note' => 'ghi chú dòng' ),
		array( 'id' => '', 'zoneKey' => 1, 'rowKind' => 'MONEY',
			'machineId' => 'M2', 'machineCode' => 'MAY-02',
			'itemCode' => 'Mã Lạ Chưa Khai',    // 🔴 không có trong danh mục
			'mBefore' => 50, 'mAfter' => '',    // 🔴 chưa gõ
			'hBefore' => '', 'hAfter' => '', 'stockActual' => '',
			'bank' => '', 'giaXung' => 5000 ),
		/* 🔴 Bảng hàng: nhân viên chỉ gõ TÊN HÀNG + MÃ MISA, ô mã hàng để TRỐNG. `itemCode` là
		   thứ KHO tra theo, nên nó phải rơi về mã MISA — thiếu bước ấy là dòng hàng không có
		   mã ⇒ duyệt xong kho không tìm được lớp tồn ⇒ giá vốn rơi về giá mua gần nhất. */
		array( 'id' => '', 'zoneKey' => 1, 'rowKind' => 'NGOAI',
			'itemCode' => '', 'itemMisa' => 'MS-CHI-CO-MISA', 'itemName' => 'Hàng gõ tay',
			'stockOpen' => 10, 'stockActual' => 8 ),
	),
);

$php_ra = VHJP_BaoCao::luu( $NV, $payload );
$g = goc( 'luu', array( array( 'the', $payload ) ), $SO, $NV, true );
t( '🔴 mã gốc lưu được', is_array( $g ) && isset( $g['ra'] ), $g );

if ( is_array( $g ) && isset( $g['ra'] ) ) {
	$goc_ra = $g['ra'][0];
	chieu( 'luu · totals', $goc_ra['totals'], $php_ra['totals'] );
	teq( 'luu · ok', $goc_ra['ok'], $php_ra['ok'] );
	teq( 'luu · reportId', $goc_ra['reportId'], $php_ra['reportId'] );
	teq( 'luu · warnCount', $goc_ra['warnCount'], $php_ra['warnCount'] );
	teq( 'luu · resetSign', $goc_ra['resetSign'], $php_ra['resetSign'] );
	teq( 'luu · số dòng trả về', count( $goc_ra['rows'] ), count( $php_ra['rows'] ) );
	foreach ( $goc_ra['rows'] as $i => $r ) { chieu( "luu · rows[$i]", $r, $php_ra['rows'][ $i ] ); }
	teq( 'luu · số cảnh báo đầu báo cáo', count( $goc_ra['headWarns'] ), count( $php_ra['headWarns'] ) );
	foreach ( $goc_ra['headWarns'] as $i => $w ) { chieu( "luu · headWarns[$i]", $w, $php_ra['headWarns'][ $i ] ); }
}

/* Và đọc lại từ sổ: mở báo cáo phải ra đúng thứ vừa lưu. */
$mo = VHJP_BaoCao::lay( $NV, 'RP-A' );
teq( 'đọc lại · đúng 2 khu vực', 2, count( $mo['zones'] ) );
teq( 'đọc lại · đúng 3 dòng', 3, count( $mo['rows'] ) );
teq( '🔴 khu vực trống tên được đặt tên', 'Khu vực 1', $mo['zones'][0]['name'] );
teq( 'ghi chú khu vực thì GIỮ (khác lúc gieo)', 'ghi chú khu', $mo['zones'][0]['note'] );
teq( 'khu vực giữ mã giao diện gửi lên', 'RP-A-Z5', $mo['zones'][1]['id'] );

$d1 = $mo['rows'][0]; $d2 = $mo['rows'][1];
teq( '🔴 mã hàng gõ thường được SNAP về cách viết chuẩn', '100JP031', $d1['itemCode'] );
teq( 'và kéo theo tên + mã MISA của danh mục', 'MS031', $d1['itemMisa'] );
teq( '🔴 mã KHÔNG có trong danh mục thì GIỮ NGUYÊN, không viết hoa lên',
	'Mã Lạ Chưa Khai', $d2['itemCode'] );
teq( '🔴 ô mã hàng để TRỐNG thì rơi về mã MISA (thứ kho tra theo)',
	'MS-CHI-CO-MISA', $mo['rows'][2]['itemCode'] );
teq( '🔴 chỉ số sau chưa gõ vẫn là ô TRỐNG', '', $d2['mAfter'] );
teq( 'ô đếm tồn chưa gõ cũng vậy', '', $d2['stockActual'] );
teq( '🔴 tiền hoàn gửi số ÂM thì lưu thành số dương', 100000, $d1['refundAmt'] );
teq( '🔴 số lượng hoàn do MÁY CHỦ suy ra, không nhận số bịa của giao diện',
	1, $d1['refundQty'] );
t( '🔴 thành tiền do MÁY CHỦ tính, không phải số giao diện gửi',
	999999999 !== $d1['amount'], $d1['amount'] );
teq( 'và tính đúng: 12 xung × 10.000đ', 120000, $d1['amount'] );
teq( '🔴 tiền mặt = thành tiền − QR, không nhận số bịa', -80000, $d1['cash'] );
teq( 'giá lấy từ danh mục (suy từ mã)', 100000, $d1['price'] );
teq( 'giá 1 xung của dòng được giữ', 10000, $d1['giaXung'] );

/* Đầu báo cáo. */
$h = $mo['head'];
teq( '🔴 lệch máy gửi số âm thì GIỮ NGUYÊN dấu (đó là số kế toán đọc)',
	true, is_numeric( $h['adjMachine'] ) );
teq( '🔴 hoàn khách gửi số âm thì lưu thành dương', 20000, $h['refundCustomer'] );
teq( 'ghi chú được lưu', 'khách trả', $h['refundNote'] );
teq( 'nhận xét được lưu', 'ổn', $h['remark'] );

// ============================================================ 5. 🔴 GIÁ XUNG BỊ ÉP Ở MÁY CHỦ
$php_b = VHJP_BaoCao::luu( $NV, array(
	'reportId' => 'RP-B',
	'zones' => array( array( 'id' => '', 'name' => 'K1' ) ),
	'rows' => array( array( 'id' => '', 'zoneKey' => 0, 'rowKind' => 'MONEY',
		'machineId' => 'M9', 'itemCode' => '100JP031',
		'mBefore' => 0, 'mAfter' => 10, 'giaXung' => 10000 ) ),
) );
$mo_b = VHJP_BaoCao::lay( $NV, 'RP-B' );
teq( '🔴 cơ sở KHÔNG bật cờ ⇒ giá 1 xung bị ÉP về mặc định, dù giao diện gửi 10.000',
	5000, $mo_b['rows'][0]['giaXung'] );
teq( 'và tiền tính theo giá bị ép', 50000, $mo_b['rows'][0]['amount'] );
$g = goc( 'luu', array( array( 'the', array( 'reportId' => 'RP-B',
	'zones' => array( array( 'id' => '', 'name' => 'K1' ) ),
	'rows' => array( array( 'id' => '', 'zoneKey' => 0, 'rowKind' => 'MONEY',
		'machineId' => 'M9', 'itemCode' => '100JP031',
		'mBefore' => 0, 'mAfter' => 10, 'giaXung' => 10000 ) ) ) ) ), $SO, $NV );
chieu( '🔴 và mã gốc cũng vậy', isset( $g[0]['totals'] ) ? $g[0]['totals'] : $g, $php_b['totals'] );

// ============================================================ 6. 🔴 DÒNG KHÔNG KHAI LOẠI
/*
 * Ở mẫu TÁCH, dòng không ghi rõ loại phải thành `MAY` (chỉ tiền). Rơi về `MONEY` là dòng đó
 * sinh cả số bán ⇒ xuất kho HAI LẦN cùng với dòng `HANG`, mà sổ vẫn cân.
 */
VHJP_BaoCao::luu( $NV, array(
	'reportId' => 'RP-C',
	'zones' => array( array( 'id' => '', 'name' => 'K1' ) ),
	'rows' => array( array( 'id' => '', 'zoneKey' => 0, 'machineId' => 'M1',
		'itemCode' => '100JP031', 'amount' => 500000, 'cash' => 300000, 'bank' => 200000,
		'cashReal' => 290000 ) ),
) );
$mo_c = VHJP_BaoCao::lay( $NV, 'RP-C' );
teq( '🔴 mẫu TÁCH · dòng không khai loại thì là MAY, không phải MONEY',
	'MAY', $mo_c['rows'][0]['rowKind'] );
teq( '🔴 và ở mẫu TÁCH thì THÀNH TIỀN là ô nhân viên gõ, máy chủ GIỮ',
	500000, $mo_c['rows'][0]['amount'] );
teq( 'tiền mặt thực tế cũng là ô gõ', 290000, $mo_c['rows'][0]['cashReal'] );

/*
 * 🔴 Ở mẫu TÁCH, ba ô tiền là ô NHÂN VIÊN GÕ — nên ô TRỐNG phải ở lại là ô trống. Đây là chỗ
 * duy nhất đo được luật ấy: ở mẫu CHUNG thì phép tính ghi đè cả ba ô nên trống hay 0 cũng ra
 * như nhau, và phép đo mù. Trống thành 0 ⇒ phép chặn nộp không chặn, lệch tiền mặt ra
 * −(tiền mặt app) ⇒ TỔNG PHẢI NỘP của cả kỳ về 0, mà sổ vẫn cân.
 *
 * 🔴 Và dòng `MAY` cũng là chỗ duy nhất đo được `abs()` của tiền hoàn theo dòng: mấy loại
 * dòng khác thì chính phép tính đã lấy trị tuyệt đối, nên chốt ở cửa vào bị che.
 */
VHJP_BaoCao::luu( $NV, array(
	'reportId' => 'RP-C',
	'zones' => array( array( 'id' => '', 'name' => 'K1' ) ),
	'rows' => array( array( 'id' => '', 'zoneKey' => 0, 'machineId' => 'M1',
		'itemCode' => '100JP031',
		'amount' => '', 'cash' => '', 'cashReal' => '', 'bank' => '',
		'refundAmt' => -50000 ) ),
) );
$mo_c2 = VHJP_BaoCao::lay( $NV, 'RP-C' );
$dc = $mo_c2['rows'][0];
teq( 'vẫn là dòng MAY của mẫu tách', 'MAY', $dc['rowKind'] );
foreach ( array( 'amount', 'cash', 'cashReal' ) as $c ) {
	teq( "🔴 mẫu TÁCH · ô tiền chưa gõ ($c) phải Ở LẠI là ô trống", '', $dc[ $c ] );
}
teq( '🔴 tiền hoàn theo dòng gửi số ÂM thì lưu thành số dương', 50000, $dc['refundAmt'] );

/* Cùng payload ấy ở mẫu CHUNG thì phải ra MONEY — chứng minh luật đi theo MẪU, không theo
   loại máy. */
teq( 'mẫu CHUNG · dòng không khai loại thì là MONEY', 'MONEY',
	VHJP_BaoCao::lay( $NV, 'RP-B' )['rows'][0]['rowKind'] );

// ============================================================ 7. 🔴 HUỶ CHỮ KÝ ĐÚNG PHẦN
$g = goc( 'diff_phan', array(
	/* không đổi gì */
	array( array( 'revMeter' => 100 ), array(), array( 'revMeter' => 100 ), array() ),
	/* đổi doanh thu ở đầu báo cáo */
	array( array( 'revMeter' => 100 ), array(), array( 'revMeter' => 200 ), array() ),
	/* đổi chỉ số đồng hồ của một dòng */
	array( array(), array( array( 'id' => 'R1', 'mAfter' => 1 ) ),
		array(), array( array( 'id' => 'R1', 'mAfter' => 2 ) ) ),
	/* đổi ô đếm tồn */
	array( array(), array( array( 'id' => 'R1', 'stockActual' => 1 ) ),
		array(), array( array( 'id' => 'R1', 'stockActual' => 2 ) ) ),
	/* thêm dòng */
	array( array(), array(), array(), array( array( 'id' => 'R2' ) ) ),
	/* đổi `cash` và `cashReal` CÙNG MỘT LƯỢNG — đầu báo cáo y nguyên */
	array( array(), array( array( 'id' => 'R1', 'cash' => 100, 'cashReal' => 100 ) ),
		array(), array( array( 'id' => 'R1', 'cash' => 200, 'cashReal' => 200 ) ) ),
), $SO );
$ca_diff = array(
	array( array( 'revMeter' => 100 ), array(), array( 'revMeter' => 100 ), array() ),
	array( array( 'revMeter' => 100 ), array(), array( 'revMeter' => 200 ), array() ),
	array( array(), array( array( 'id' => 'R1', 'mAfter' => 1 ) ),
		array(), array( array( 'id' => 'R1', 'mAfter' => 2 ) ) ),
	array( array(), array( array( 'id' => 'R1', 'stockActual' => 1 ) ),
		array(), array( array( 'id' => 'R1', 'stockActual' => 2 ) ) ),
	array( array(), array(), array(), array( array( 'id' => 'R2' ) ) ),
	array( array(), array( array( 'id' => 'R1', 'cash' => 100, 'cashReal' => 100 ) ),
		array(), array( array( 'id' => 'R1', 'cash' => 200, 'cashReal' => 200 ) ) ),
);
foreach ( $ca_diff as $i => $ca ) {
	$mong = isset( $g[ $i ] ) ? $g[ $i ] : null;
	$thuc = VHJP_BaoCao::diff_phan( $ca[0], $ca[1], $ca[2], $ca[3] );
	sort( $mong ); sort( $thuc );
	teq( "diff_phan[$i]", $mong, $thuc );
}
teq( '🔴 không sửa gì thì KHÔNG huỷ chữ ký oan', array(),
	VHJP_BaoCao::diff_phan( array( 'revMeter' => 100 ), array(), array( 'revMeter' => 100 ), array() ) );
teq( '🔴 sửa ô ĐẾM TỒN chỉ đụng phần HÀNG HOÁ', array( VHJP_BaoCao::PHAN_HANG ),
	VHJP_BaoCao::diff_phan( array(), array( array( 'id' => 'R1', 'stockActual' => 1 ) ),
		array(), array( array( 'id' => 'R1', 'stockActual' => 2 ) ) ) );
teq( '🔴 sửa CHỈ SỐ ĐỒNG HỒ chỉ đụng phần DOANH THU', array( VHJP_BaoCao::PHAN_TIEN ),
	VHJP_BaoCao::diff_phan( array(), array( array( 'id' => 'R1', 'mAfter' => 1 ) ),
		array(), array( array( 'id' => 'R1', 'mAfter' => 2 ) ) ) );
$ca_them = VHJP_BaoCao::diff_phan( array(), array(), array(), array( array( 'id' => 'R2' ) ) );
sort( $ca_them );
teq( '🔴 THÊM hoặc XOÁ dòng thì đụng CẢ HAI phần',
	array( VHJP_BaoCao::PHAN_TIEN, VHJP_BaoCao::PHAN_HANG ), $ca_them );

/* Trên báo cáo thật: ký GỘP đời cũ, sửa phần doanh thu. */
$truoc_d = VHJP_BaoCao::chu_ky( VHJP_Nguon::tim_mot( 'JP_Reports', 'id', 'RP-D' ) );
teq( 'trước khi sửa: chữ ký gộp tính là ký cả hai phần', true, $truoc_d['duCaHai'] );
VHJP_BaoCao::luu( $NV, array(
	'reportId' => 'RP-D',
	'zones' => array( array( 'id' => 'RP-D-Z1', 'name' => 'Tầng 1', 'clusterId' => 'C1' ) ),
	'rows' => array( array( 'id' => 'RP-D-R1', 'zoneKey' => 0, 'rowKind' => 'MONEY',
		'machineId' => 'M1', 'itemCode' => '100JP031',
		'mBefore' => 100, 'mAfter' => 120,       // 🔴 đổi chỉ số -> phần DOANH THU
		'hBefore' => 30, 'hAfter' => 20, 'stockActual' => 10, 'giaXung' => 5000 ) ),
) );
$sau_d = VHJP_BaoCao::chu_ky( VHJP_Nguon::tim_mot( 'JP_Reports', 'id', 'RP-D' ) );
teq( '🔴 sửa doanh thu ⇒ chữ ký DOANH THU bị huỷ', '', $sau_d['rev']['by'] );
teq( '🔴 nhưng chữ ký HÀNG HOÁ được GIỮ — không bắt kế toán soát lại bảng hàng',
	'ketoan', $sau_d['stock']['by'] );
teq( 'và chữ ký gộp bị xoá để nó không hồi sinh cái vừa huỷ', '',
	VHJP_Doc::str( VHJP_Nguon::tim_mot( 'JP_Reports', 'id', 'RP-D' )['apprBy'] ) );
/*
 * 🔴 Trên báo cáo có HAI CHỮ KÝ RIÊNG (không phải chữ ký gộp). Ở báo cáo ký gộp, nhánh "cấp
 * lại chữ ký cho phần không sửa" che mất phép đo: huỷ nhầm rồi cấp lại thì nhìn vào kết quả
 * không thấy gì khác. Nên phải có một ca không đi qua nhánh ấy.
 */
$truoc_h = VHJP_BaoCao::chu_ky( VHJP_Nguon::tim_mot( 'JP_Reports', 'id', 'RP-H' ) );
teq( 'trước khi sửa: đủ hai chữ ký riêng', true, $truoc_h['duCaHai'] );
VHJP_BaoCao::luu( $NV, array(
	'reportId' => 'RP-H',
	'zones' => array( array( 'id' => 'RP-H-Z1', 'name' => 'Tầng 1', 'clusterId' => 'C1' ) ),
	'rows' => array( array( 'id' => 'RP-H-R1', 'zoneKey' => 0, 'rowKind' => 'MONEY',
		'machineId' => 'M1', 'itemCode' => '100JP031',
		'mBefore' => 100, 'mAfter' => 130,       // 🔴 chỉ đổi CHỈ SỐ -> chỉ phần DOANH THU
		'hBefore' => 20, 'hAfter' => 10, 'stockActual' => 10, 'giaXung' => 5000 ) ),
) );
$sau_h = VHJP_BaoCao::chu_ky( VHJP_Nguon::tim_mot( 'JP_Reports', 'id', 'RP-H' ) );
teq( '🔴 chữ ký RIÊNG · sửa doanh thu ⇒ huỷ chữ ký doanh thu', '', $sau_h['rev']['by'] );
teq( '🔴 chữ ký RIÊNG · và chữ ký hàng hoá KHÔNG bị đụng tới', 'ketoan', $sau_h['stock']['by'] );
/* ⚠️ Có GIÂY ở cuối, khác bản Sheets một chút: bên kia cột này giữ đối tượng ngày và được in
   ra theo khuôn `yyyy-MM-dd HH:mm`, còn bên này `DATETIME` của MySQL đọc lại là chuỗi đã có
   sẵn giây. `ngay_gio()` chép đúng bản gốc (chuỗi thì trả nguyên), nên chỗ lệch nằm ở tầng
   lưu trữ chứ không ở hàm — và nó chỉ đổi cách HIỆN RA, không đổi con số nào. */
teq( 'thời điểm ký hàng hoá cũng nguyên vẹn', '2026-04-20 09:05:00', $sau_h['stock']['at'] );

$nk = VHJP_Nguon::tim( 'JP_Audit', 'action', 'RESET_SIGN' );
t( '🔴 và ghi nhật ký việc huỷ chữ ký', count( $nk ) > 0, count( $nk ) );

// ============================================================ 8. QUYỀN & TRẠNG THÁI
$nem = function ( $f ) { try { $f(); return ''; } catch ( Throwable $e ) { return $e->getMessage(); } };
teq( 'báo cáo không tồn tại ⇒ nói rõ', 'Không tìm thấy báo cáo',
	$nem( function () use ( $NV ) { VHJP_BaoCao::luu( $NV, array( 'reportId' => 'KHONGCO' ) ); } ) );
$loi = $nem( function () use ( $NV ) { VHJP_BaoCao::luu( $NV, array( 'reportId' => 'RP-E' ) ); } );
t( '🔴 KHÔNG lưu đè được báo cáo của người khác',
	false !== mb_strpos( $loi, 'không sửa được' ), $loi );
$loi = $nem( function () use ( $NV ) { VHJP_BaoCao::luu( $NV, array( 'reportId' => 'RP-F' ) ); } );
t( '🔴 báo cáo ĐÃ NỘP thì chính chủ cũng không lưu đè',
	false !== mb_strpos( $loi, 'CHO_DUYET' ), $loi );
teq( 'và sổ của báo cáo người khác không suy suyển', 0,
	count( VHJP_Nguon::tim( 'JP_Rows', 'reportId', 'RP-E' ) ) );
/* 🔴 CỦA CHÍNH MÌNH nhưng ở cơ sở KHÔNG được gán — ca duy nhất đo được chốt quyền cơ sở, vì
   mọi ca khác đã bị chốt "chủ sở hữu" chặn trước. */
teq( '🔴 báo cáo của chính mình ở cơ sở KHÔNG được gán ⇒ vẫn CHẶN',
	'Không có quyền với cơ sở này',
	$nem( function () use ( $NV ) { VHJP_BaoCao::luu( $NV, array( 'reportId' => 'RP-G' ) ); } ) );

$loi = $nem( function () use ( $NV2 ) { VHJP_BaoCao::luu( $NV2, array( 'reportId' => 'RP-A' ) ); } );
t( 'người khác đụng vào báo cáo của mình thì bị chặn ngay ở cửa chủ sở hữu',
	false !== mb_strpos( $loi, 'Nam' ), $loi );

// ============================================================ 9. XOÁ SẠCH RỒI GHI LẠI
/* Lưu lần hai với ÍT dòng hơn: dòng cũ phải BIẾN MẤT, không được nằm lại thành rác vô hình. */
VHJP_BaoCao::luu( $NV, array(
	'reportId' => 'RP-A',
	'zones' => array( array( 'id' => '', 'name' => 'Chỉ một khu' ) ),
	'rows' => array( array( 'id' => '', 'zoneKey' => 0, 'rowKind' => 'MONEY',
		'machineId' => 'M1', 'itemCode' => '100JP031', 'mBefore' => 0, 'mAfter' => 1 ) ),
) );
teq( '🔴 lưu lại với ít dòng hơn ⇒ dòng cũ bị xoá hẳn', 1,
	count( VHJP_Nguon::tim( 'JP_Rows', 'reportId', 'RP-A' ) ) );
teq( 'khu vực cũng vậy', 1, count( VHJP_Nguon::tim( 'JP_Zones', 'reportId', 'RP-A' ) ) );
teq( '🔴 và KHÔNG đụng tới dòng của báo cáo khác', 1,
	count( VHJP_Nguon::tim( 'JP_Rows', 'reportId', 'RP-D' ) ) );

/* Lưu một báo cáo RỖNG: tổng phải về 0, không được giữ số cũ. */
VHJP_BaoCao::luu( $NV, array( 'reportId' => 'RP-A', 'zones' => array(), 'rows' => array() ) );
$rong = VHJP_Nguon::tim_mot( 'JP_Reports', 'id', 'RP-A' );
teq( '🔴 báo cáo rỗng ⇒ doanh thu về 0, không giữ số của lượt lưu trước',
	0, VHJP_Doc::num( $rong['revMeter'] ) );
teq( 'và tổng phải nộp cũng về 0', 0, VHJP_Doc::num( $rong['totalSubmit'] ) );

// ============================================================ 10. KHOÁ GHI & CỔNG
t( '🔴 lấy được khoá ghi (máy chủ không có GET_LOCK thì cũng phải đi tiếp được)',
	VHJP_Nguon::lay_khoa( 'RP-A' ) );
t( 'và trả được khoá', VHJP_Nguon::tra_khoa( 'RP-A' ) );

require_once $plg . 'class-vhjp-cong.php';
t( 'cổng khai jpSaveReport', isset( VHJP_Cong::map()['jpSaveReport'] ) );
t( 'jpSaveReport không còn ở bảng chưa làm',
	! in_array( 'jpSaveReport', VHJP_Cong::chua_lam(), true ) );
t( '🔴 jpSaveReport gác vai NHÂN VIÊN', in_array( 'jpSaveReport', VHJP_Cong::chi_nhan_vien(), true ) );

// ============================================================ kết
if ( $truot ) {
	echo "HỎNG: " . count( $truot ) . "\n";
	foreach ( $truot as $x ) { echo "  ✗ $x\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — đường lưu báo cáo khớp mã gốc chạy thật.\n";
