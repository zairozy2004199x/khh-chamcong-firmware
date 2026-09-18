<?php
/**
 * KIỂM TÁCH "CƠ SỞ CHẤM CÔNG" KHỎI "CƠ SỞ QUẢN LÝ".
 *
 * =================================================================================================
 * 🔴 VÌ SAO BÀI NÀY CẦN
 * =================================================================================================
 * Anh Thắng 18/09/2026: *"nếu chấm công thì xem quản thân chứ, còn quản lý mới xem được cả cửa
 * hàng"*.
 *
 * Trước bản này `co_quyen_coso()` hỏi `ds_coso_cua()` — MỌI cơ sở đã tích trong thẻ phiên. Nên hễ
 * ai chấm công ở đâu là quản được ở đó: xem cả bảng công cửa hàng ấy, duyệt đơn của người ở đó,
 * thêm nhân sự vào đó, tải tệp tuần của nó. Một cửa hàng trưởng sang cơ sở khác làm nhờ một ca là
 * lập tức quản được cả cơ sở ấy — và không ai thấy gì bất thường, vì màn hình vẫn vẽ ra bình
 * thường.
 *
 * `co_quyen_coso()` là CÁI CỔNG của gần như mọi việc trong hệ, nên bài này canh nó trực tiếp, rồi
 * canh thêm mấy cửa lớn đi qua nó.
 *
 * Chạy: php tools/test/kiem-coso-quan.php
 */

$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
vhcc_test_boot( $goc . '/wordpress/vhcp-cham-cong' );

$dat = 0; $truot = array();

register_shutdown_function( function () {
	global $dat, $truot;
	$e = error_get_last();
	if ( ! $e || ! in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) { return; }
	echo "\n🔴 BÀI KIỂM CHẾT GIỮA ĐƯỜNG: " . $e['message'] . "\n   tại " . $e['file'] . ':' . $e['line'] . "\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT (tới lúc chết): $dat\n";
} );

function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( null === $them ? '' : "\n      → " . ( is_scalar( $them ) ? $them : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) );
}
function teq( $ten, $mong, $thuc ) {
	t( $ten . ' (mong ' . json_encode( $mong, JSON_UNESCAPED_UNICODE ) . ')', $mong === $thuc, $thuc );
}

global $wpdb;

/* Dựng đúng cảnh chị Mai Anh trong ảnh anh Thắng gửi:
   · FZ_LTVT      — cơ sở CHÍNH, vừa chấm công vừa quản;
   · POSH_HCM     — có tích, CHẤM CÔNG ở đây, nhưng KHÔNG quản;
   · FARM_PT      — "chỉ QL": quản, không chấm công. */
$CHINH = 'FZ_LTVT';
$LAM   = 'POSH_HCM';
$QL    = 'FARM_PT';
$LA    = 'KHONG_LIEN_QUAN';

$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array(
	'ma_nv' => 'CQ_CHT', 'ho_ten' => 'Chị Trưởng', 'vai_tro' => 'Cửa hàng trưởng',
	'cua_hang' => $CHINH, 'coso_phu' => $LAM . ', ' . $QL,
	'coso_ql' => $QL, 'coso_quan' => $CHINH,
	'trang_thai_lam_viec' => 'Đang làm' ) );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array(
	'ma_nv' => 'CQ_NV', 'ho_ten' => 'Em Nhân Viên', 'vai_tro' => 'Nhân viên',
	'cua_hang' => $LAM, 'trang_thai_lam_viec' => 'Đang làm' ) );

/* Thẻ phiên chở CẢ BA cơ sở — đúng như ngoài đời, và đúng chỗ bản cũ đọc nhầm. */
$CHT = array( 'name' => 'Chị Trưởng', 'role' => VHCC_Vai::CHT, 'ma_nv' => 'CQ_CHT',
	'coso' => $CHINH . ', ' . $LAM . ', ' . $QL );
$NV  = array( 'name' => 'Em Nhân Viên', 'role' => VHCC_Vai::NV, 'ma_nv' => 'CQ_NV', 'coso' => $LAM );
$QLY = array( 'name' => 'Anh Quản Lý', 'role' => VHCC_Vai::QL, 'ma_nv' => 'CQ_QL', 'coso' => '' );

$hs = VHCC_NhanSu::ho_so( 'CQ_CHT' );

/* ================================================================= ba danh sách */

echo "— ba danh sách —\n";
$cham = VHCC_NhanSu::ds_coso_cham( $hs );
sort( $cham );
teq( 'cơ sở CHẤM CÔNG = đã tích trừ "chỉ QL"', array( $CHINH, $LAM ), $cham );

$quan = VHCC_NhanSu::ds_coso_quan( $hs );
sort( $quan );
/* 🔴 ĐÂY LÀ CẢ Ý CỦA THAY ĐỔI NÀY. POSH_HCM có trong danh sách CHẤM, KHÔNG có trong danh sách
   QUẢN — chị ấy đi làm ở đó nhưng không quản ai ở đó. */
teq( '🔴 cơ sở QUẢN LÝ = "chỉ QL" + cột coso_quan, KHÔNG gồm nơi chỉ đi làm',
	array( $QL, $CHINH ), $quan );
t( '🔴 nơi chỉ đi làm KHÔNG nằm trong danh sách quản', ! in_array( $LAM, $quan, true ), $quan );
t( 'nhưng vẫn nằm trong danh sách chấm công', in_array( $LAM, $cham, true ), $cham );

teq( 'cột thô KHÔNG nuốt "chỉ QL" vào — hai cột ở riêng', array( $CHINH ),
	VHCC_NhanSu::ds_coso_quan_tho( $hs ) );

/* ================================================================= cái cổng */

echo "— cổng co_quyen_coso —\n";
t( '🔴 cơ sở CHÍNH: quản được (vừa làm vừa quản)', VHCC_NhanSu::co_quyen_coso( $CHT, $CHINH ) );
t( '🔴 cơ sở "chỉ QL": quản được',                  VHCC_NhanSu::co_quyen_coso( $CHT, $QL ) );
/* 🔴 CHỐT CHÍNH CỦA CẢ BÀI. */
t( '🔴 cơ sở CHỈ ĐI LÀM: KHÔNG quản được',        ! VHCC_NhanSu::co_quyen_coso( $CHT, $LAM ) );
t( 'cơ sở không liên quan: càng không',            ! VHCC_NhanSu::co_quyen_coso( $CHT, $LA ) );
t( 'nhân viên thường không quản đâu cả',           ! VHCC_NhanSu::co_quyen_coso( $NV, $LAM ) );
t( 'Quản lý trở lên vẫn xem được mọi cơ sở',        VHCC_NhanSu::co_quyen_coso( $QLY, $LA ) );

/* ================================================================= mấy cửa đi qua cổng */

echo "— mấy cửa lớn —\n";
/* Tệp bảng công tuần: trước bản này chị ấy tải được cả POSH_HCM. */
$TUAN = VHCC_TuanCong::tuan_truoc();
teq( 'tải được tệp tuần của cơ sở mình quản', '',
	VHCC_TuanCong::vi_sao_khong_tai( $CHT, $CHINH, $TUAN ) );
t( '🔴 KHÔNG tải được tệp tuần của nơi chỉ đi làm',
	'' !== VHCC_TuanCong::vi_sao_khong_tai( $CHT, $LAM, $TUAN ) );

/* Duyệt đơn bù cấp một. */
$HOM = (string) current_time( 'Y-m-d' );
$QUA = gmdate( 'Y-m-d', strtotime( $HOM . ' 00:00:00 UTC' ) - 86400 );
$wpdb->insert( VHCC_DB::t( 'xin_bu' ), array( 'coso' => $LAM, 'ngay' => $QUA,
	'ma_nv' => 'CQ_NV', 'ho_ten' => 'Em Nhân Viên', 'vao' => '08:00', 'ra' => '17:00',
	'ly_do' => 'quên bấm máy hôm ấy', 'trang_thai' => VHCC_XinBu::CHO_CHT,
	'tao_luc' => current_time( 'mysql' ) ) );
$id = (int) $wpdb->insert_id;
$r = VHCC_XinBu::duyet_cht( $CHT, $id, true );
t( '🔴 KHÔNG duyệt được đơn của nơi mình chỉ đi làm', empty( $r['ok'] ), $r );
t( 'và câu chối nói đúng chuyện cửa hàng khác',
	! empty( $r['error'] ) && false !== mb_strpos( $r['error'], 'cửa hàng khác' ), $r );

/* Danh sách cơ sở của tab Cửa hàng trên trạm — `VHCC_CuaHang::ds_coso()` lọc qua chính cổng ấy. */
$ds_tram = VHCC_CuaHang::ds_coso( $CHT );
sort( $ds_tram );
teq( '🔴 tab Cửa hàng chỉ bày cơ sở mình QUẢN', array( $QL, $CHINH ), $ds_tram );
t( '🔴 và mọi cơ sở nó bày ra đều bấm được', (function () use ( $CHT, $ds_tram ) {
	foreach ( $ds_tram as $x ) {
		if ( ! VHCC_NhanSu::co_quyen_coso( $CHT, $x ) ) { return false; }
	}
	return true;
} )() );

/* ================================================================= lưu lại */

echo "— lưu lại —\n";
$AD = array( 'name' => 'Quản trị', 'role' => VHCC_Vai::ADMIN, 'coso' => '', 'ma_nv' => 'CQ_AD' );

/* Bỏ tích "quản lý" ở cơ sở chính -> mất quyền ngay trong lượt ấy (không còn nhớ bản cũ). */
$r = VHCC_NhanSu::dat_ds_coso( $AD, 'CQ_CHT', array( $CHINH, $LAM, $QL ), $CHINH,
	array( $QL ), array() );
t( 'lưu được', ! empty( $r['ok'] ), $r );
t( '🔴 bỏ tích quản lý thì mất quyền NGAY, không nhớ bản cũ',
	! VHCC_NhanSu::co_quyen_coso( $CHT, $CHINH ) );
t( 'nhưng "chỉ QL" vẫn còn nguyên quyền', VHCC_NhanSu::co_quyen_coso( $CHT, $QL ) );
teq( 'và cơ sở chấm công KHÔNG đổi', array( $CHINH, $LAM ),
	( function () { $c = VHCC_NhanSu::ds_coso_cham( VHCC_NhanSu::ho_so( 'CQ_CHT' ) ); sort( $c ); return $c; } )() );

/* Tích lại. */
$r = VHCC_NhanSu::dat_ds_coso( $AD, 'CQ_CHT', array( $CHINH, $LAM, $QL ), $CHINH,
	array( $QL ), array( $CHINH ) );
t( 'tích lại thì có quyền lại', VHCC_NhanSu::co_quyen_coso( $CHT, $CHINH ) );

/* 🔴 BỎ TÍCH MỘT CƠ SỞ THÌ CỜ QUẢN CỦA NÓ PHẢI RƠI THEO. Cờ mồ côi mà còn tính thì lượt sau
   tích lại cơ sở ấy là người ta lặng lẽ quản được nó. */
VHCC_NhanSu::dat_ds_coso( $AD, 'CQ_CHT', array( $CHINH ), $CHINH, array(), null );
VHCC_NhanSu::dat_ds_coso( $AD, 'CQ_CHT', array( $CHINH, $LAM ), $CHINH, array(), null );
t( '🔴 bỏ tích rồi tích lại thì KHÔNG tự quản được cơ sở ấy',
	! VHCC_NhanSu::co_quyen_coso( $CHT, $LAM ) );

/* ================================================================= gieo lúc nâng cấp */

echo "— gieo lúc nâng cấp —\n";
/* 🔴 Không gieo thì cài bản này xong là cả chuỗi mất quyền cùng lúc, không ai đoán ra vì sao. */
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'CQ_CU1', 'ho_ten' => 'Trưởng Cũ',
	'vai_tro' => 'Cửa hàng trưởng', 'cua_hang' => 'QUAY_CU', 'coso_quan' => '',
	'trang_thai_lam_viec' => 'Đang làm' ) );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'CQ_CU2', 'ho_ten' => 'Nhân Viên Cũ',
	'vai_tro' => 'Nhân viên', 'cua_hang' => 'QUAY_CU', 'coso_quan' => '',
	'trang_thai_lam_viec' => 'Đang làm' ) );

VHCC_NhanSu::gieo_coso_quan();
VHCC_NhanSu::quen_coso_quan();

teq( '🔴 cửa hàng trưởng cũ được gieo cơ sở chính', 'QUAY_CU',
	(string) VHCC_NhanSu::ho_so( 'CQ_CU1' )['coso_quan'] );
teq( '🔴 nhân viên KHÔNG được gieo — gieo là phát quyền quản cho cả chuỗi', '',
	(string) VHCC_NhanSu::ho_so( 'CQ_CU2' )['coso_quan'] );

/* Chạy lại lượt nâng cấp không được đè lên thứ người ta đã khai tay. */
$wpdb->update( VHCC_DB::t( 'nhan_vien' ), array( 'coso_quan' => 'QUAY_KHAC' ),
	array( 'ma_nv' => 'CQ_CU1' ) );
VHCC_NhanSu::gieo_coso_quan();
teq( '🔴 chạy lại lượt gieo KHÔNG đè lên thứ đã khai tay', 'QUAY_KHAC',
	(string) VHCC_NhanSu::ho_so( 'CQ_CU1' )['coso_quan'] );

/* ================================================================= tài khoản không mã NV */

echo "— tài khoản không mã NV —\n";
/* 🔴 Tài khoản dựng ở màn Người dùng không gắn mã nên không tra được hồ sơ. Khoá cứng họ lại là
   mấy tài khoản quản trị cũ mất sạch quyền ngay lúc cài, mà không ai hiểu vì sao. */
$KHONG_MA = array( 'name' => 'Trưởng Không Mã', 'role' => VHCC_Vai::CHT, 'ma_nv' => '',
	'coso' => 'CS_X, CS_Y' );
t( '🔴 không có mã NV thì lùi về lối cũ, không mất sạch quyền',
	VHCC_NhanSu::co_quyen_coso( $KHONG_MA, 'CS_X' )
	&& VHCC_NhanSu::co_quyen_coso( $KHONG_MA, 'CS_Y' ) );
t( 'nhưng vẫn chỉ trong mấy cơ sở thẻ phiên chở',
	! VHCC_NhanSu::co_quyen_coso( $KHONG_MA, 'CS_Z' ) );

echo "\n";
if ( $truot ) {
	echo '🔴 HỎNG ' . count( $truot ) . " phép thử:\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "✓ ĐẠT: $dat phép thử — chấm công ở đâu KHÔNG còn nghĩa là quản được ở đó.\n";
