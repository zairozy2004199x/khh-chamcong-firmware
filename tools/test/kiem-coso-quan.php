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
/* Tệp bảng công tháng: trước bản này chị ấy tải được cả POSH_HCM. */
$TUAN = VHCC_TuanCong::dau_thang( (string) current_time( 'Y-m-d' ) );
teq( 'tải được tệp tháng của cơ sở mình quản', '',
	VHCC_TuanCong::vi_sao_khong_tai( $CHT, $CHINH, $TUAN ) );
t( '🔴 KHÔNG tải được tệp tháng của nơi chỉ đi làm',
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

/* ================================================================= xem quản thân */

echo "— cơ sở chỉ đi làm: xem công CỦA MÌNH —\n";
/* 🔴 VẾ THỨ HAI của câu anh Thắng nói: *"nếu chấm công thì xem quản thân chứ"*. Bản 4.50.0 chỉ
   làm vế đầu rồi chối thẳng vế sau — ô xổ bày ra POSH_HCM mà bấm vào ăn đúng một câu "Không có
   quyền cơ sở này." Bày một lựa chọn rồi chối nó tệ hơn cả không bày. */
VHCC_NhanSu::dat_ds_coso( $AD, 'CQ_CHT', array( $CHINH, $LAM, $QL ), $CHINH, array( $QL ), array( $CHINH ) );
$TH2 = '2026-09';
foreach ( array( 'CQ_CHT', 'CQ_NV' ) as $m_x ) {
	$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => $LAM, 'ngay' => $TH2 . '-02',
		'ma_nv' => $m_x, 'ho_ten' => $m_x, 'gio_vao_giay' => 28800, 'gio_ra_giay' => 61200,
		'hau_to' => '', 'nguon' => 'may' ) );
}

$b = VHCC_Cham::bang_cham_cong( $CHT, $LAM, $TH2 );
t( '🔴 cơ sở chỉ đi làm KHÔNG còn chối thẳng — mở được', ! empty( $b['ok'] ), $b );
t( '🔴 và màn biết mình đang ở mức hẹp', ! empty( $b['riengMinh'] ) );
$ma_thay = array();
foreach ( (array) $b['hang'] as $h_x ) { $ma_thay[ (string) $h_x['maNV'] ] = 1; }
teq( '🔴 chỉ thấy công CỦA MÌNH, không thấy người khác', array( 'CQ_CHT' ), array_keys( $ma_thay ) );

/* Ở cơ sở mình QUẢN thì vẫn thấy cả cửa hàng — vế đầu không bị nới theo. */
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => $CHINH, 'ngay' => $TH2 . '-02',
	'ma_nv' => 'CQ_NV', 'ho_ten' => 'Em Nhân Viên', 'gio_vao_giay' => 28800,
	'gio_ra_giay' => 61200, 'hau_to' => '', 'nguon' => 'may' ) );
$b2 = VHCC_Cham::bang_cham_cong( $CHT, $CHINH, $TH2 );
t( 'cơ sở mình QUẢN thì không bị hẹp', empty( $b2['riengMinh'] ), $b2 );
$ma2 = array();
foreach ( (array) $b2['hang'] as $h_x ) { $ma2[ (string) $h_x['maNV'] ] = 1; }
t( '🔴 và thấy cả người khác ở đó', isset( $ma2['CQ_NV'] ), array_keys( $ma2 ) );

/* Cơ sở không dính dáng gì thì vẫn chối — nới vế hai không được nới thành nới hết. */
$b3 = VHCC_Cham::bang_cham_cong( $CHT, $LA, $TH2 );
t( '🔴 cơ sở không liên quan thì VẪN chối', empty( $b3['ok'] ), $b3 );

t( 'co_cham_coso: đúng ở nơi mình đi làm', VHCC_NhanSu::co_cham_coso( $CHT, $LAM ) );
t( 'co_cham_coso: SAI ở cơ sở "chỉ QL" (không chấm công ở đó)',
	! VHCC_NhanSu::co_cham_coso( $CHT, $QL ) );
t( 'co_cham_coso: sai ở cơ sở không liên quan', ! VHCC_NhanSu::co_cham_coso( $CHT, $LA ) );

/* ================================================================= tab Nhân sự: danh bạ */

echo "— tab Nhân sự: ai đi làm ở đó cũng xem được danh bạ —\n";
/* Anh Thắng 18/09/2026: *"ai quản lý hoặc chấm công thì xem được hết, vì chỉ xem được họ và
   sđt để nv còn biết ai quản lý, ai cửa hàng trưởng, chứ không ảnh hưởng gì"*. */
$db = VHCC_NhanSu::danh_ba( $CHT, $LAM );
$ten_db = array();
foreach ( $db as $x ) { $ten_db[ (string) $x['ma_nv'] ] = 1; }
t( '🔴 cơ sở chỉ đi làm: VẪN xem được danh bạ', isset( $ten_db['CQ_NV'] ), array_keys( $ten_db ) );

/* 🔴 NHƯNG ĐÚNG BỐN CỘT. Danh bạ để gọi nhau, không phải hồ sơ nhân sự. */
$cot = array_keys( (array) $db[0] );
sort( $cot );
teq( '🔴 danh bạ trả đúng mấy cột đã chọn, không hơn',
	array( 'chuc_vu', 'coso_phu', 'cua_hang', 'ho_ten', 'ma_nv', 'sdt' ), $cot );
foreach ( array( 'pin_dang_nhap', 'cccd', 'luong_co_ban', 'so_tai_khoan', 'ngay_sinh' ) as $c_x ) {
	t( '🔴 danh bạ KHÔNG trả ' . $c_x, ! in_array( $c_x, $cot, true ) );
}

teq( 'người ngoài cuộc thì danh bạ rỗng', array(), VHCC_NhanSu::danh_ba( $CHT, $LA ) );
teq( 'nhân viên thường cũng xem được danh bạ chỗ mình làm', 1,
	count( array_filter( VHCC_NhanSu::danh_ba( $NV, $LAM ),
		function ( $x ) { return 'CQ_NV' === (string) $x['ma_nv']; } ) ) );

/* 🔴 XEM ĐƯỢC KHÔNG CÓ NGHĨA LÀ SỬA ĐƯỢC. Đổi số điện thoại và CẤP PIN cho người khác thì chỉ
   người QUẢN mới được — `co_quyen_coso()` vẫn là cổng của mấy việc ấy. */
t( '🔴 xem danh bạ được nhưng KHÔNG quản cơ sở ấy',
	! VHCC_NhanSu::co_quyen_coso( $CHT, $LAM ) );

/* ================================================================= màn thật, CẢ HAI cách tính */

echo "— dựng trang thật rồi soi xem có lọt tên ai không —\n";
/* 🔴 SOI TRANG ĐÃ VẼ, KHÔNG SOI HÀM. Lọc ở `bang_cham_cong()` xanh không có nghĩa là màn kín:
   màn còn mấy khối đọc THẲNG cả cơ sở. Riêng lưới Văn phòng (`cach_tinh = cong`) đi một nhánh
   khác hẳn, và chính nhánh ấy rò ở 4.52.0 — chốt gác viết `$b['riengMinh']` trong một hàm
   KHÔNG CÓ `$b`, nên biến không tồn tại, `empty()` trả true, và chốt không bao giờ nổ. PHP
   không kêu một tiếng nào.
   ⚠️ CHẠY CẢ 'gio' LẪN 'cong'. Bài dò đầu tiên của em chỉ chạy 'gio' nên báo sạch, trong khi
      cơ sở anh Thắng đang mở là 'cong'. Thiếu một nhánh là thiếu cả phép thử. */
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'CQ_KHAC', 'ho_ten' => 'NGƯỜI KHÁC HẲN',
	'vai_tro' => 'Nhân viên', 'cua_hang' => $LAM, 'trang_thai_lam_viec' => 'Đang làm' ) );

/* 🔴 NGƯỜI CHƯA CHẤM NGÀY NÀO — ĐÂY LÀ CA BA PHÉP THỬ TRƯỚC ĐỀU BỎ SÓT.
   Lưới dựng thêm "hàng trống" cho người có hồ sơ mà chưa chấm, bằng một vòng đọc THẲNG sổ nhân
   sự — nên phép lọc trên `rows`/`hang` không chạm tới. Gieo toàn người CÓ chấm thì mọi phép
   thử đều xanh, mà màn thật vẫn bày ra cả cửa hàng: đúng ba lần anh Thắng phải chụp lại. */
/* ⚠️ VÀ NGƯỜI ẤY PHẢI THUỘC MỘT CƠ SỞ MÌNH CÓ QUẢN. Vòng dựng hàng trống đi qua
   `ds_nhan_vien()`, hàm này gác từng hồ sơ bằng `co_quyen_ho_so()` — người chỉ thuộc riêng
   cơ sở POSH thì đằng nào cũng không lọt, nên gieo như thế là dựng một cảnh KHÔNG BAO GIỜ
   xảy ra và phép thử xanh oan (em đã gieo sai đúng kiểu ấy một lượt).
   Cảnh THẬT trong ảnh anh Thắng: mấy người "còn làm ở FZ_LTVT / FARM_PT" — tức thuộc cơ sở
   chị ấy CÓ quản, và cũng làm ở POSH. Hồ sơ lọt qua cổng, rồi mọc một hàng trống ở POSH. */
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'CQ_TRONG', 'ho_ten' => 'NGƯỜI CHƯA CHẤM',
	'vai_tro' => 'Nhân viên', 'cua_hang' => $CHINH, 'coso_phu' => $LAM,
	'trang_thai_lam_viec' => 'Đang làm' ) );
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => $LAM, 'ngay' => $TH2 . '-03',
	'ma_nv' => 'CQ_KHAC', 'ho_ten' => 'NGƯỜI KHÁC HẲN', 'gio_vao_giay' => 28800,
	'gio_ra_giay' => 61200, 'hau_to' => '', 'nguon' => 'may' ) );

$tok_cht = VHCC_Auth::phat_token( 'Chị Trưởng', 'Cửa hàng trưởng', $CHINH . ', ' . $LAM, 'CQ_CHT' );

/* Đọc TÊN của từng dòng trong lưới — soi đúng ô tên, không soi cả trang. */
function cq_ten_hang( $trang ) {
	$m = array();
	preg_match_all( '#class="ten-nv"[^>]*>(.*?)</a>#s', $trang, $m );
	return isset( $m[1] ) ? $m[1] : array();
}
function cq_co_ten( $trang, $ten ) {
	foreach ( cq_ten_hang( $trang ) as $x ) {
		if ( false !== mb_strpos( $x, $ten ) ) { return true; }
	}
	return false;
}

foreach ( array( 'gio', 'cong' ) as $cach ) {
	$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'cai_dat' ) . " WHERE khoa='CACH_TINH_COSO'" );
	$wpdb->insert( VHCC_DB::t( 'cai_dat' ), array( 'khoa' => 'CACH_TINH_COSO',
		'gia_tri' => wp_json_encode( array( $LAM => $cach ) ) ) );
	teq( 'dựng được cảnh cách tính ' . $cach, $cach, VHCC_Luong::cach_tinh( $LAM ) );

	$_COOKIE = array( VHCC_Web::COOKIE => $tok_cht );
	$_GET    = array( 'man' => 'cham', 'ccs' => $LAM, 'cth' => $TH2 );
	$_POST   = array();
	ob_start();
	VHCC_Web::phuc_vu();
	$trang = ob_get_clean();
	$_GET  = array();
	$_COOKIE = array();

	$ro = array();
	foreach ( array( 'NGƯỜI KHÁC HẲN', 'CQ_KHAC', 'Em Nhân Viên', 'CQ_NV',
		'NGƯỜI CHƯA CHẤM', 'CQ_TRONG' ) as $x_r ) {
		if ( false !== mb_strpos( $trang, $x_r ) ) { $ro[] = $x_r; }
	}
	t( '🔴 [' . $cach . '] KHÔNG lọt tên hay mã người khác ra trang', ! $ro, $ro );
	/* 🔴 ĐẾM DÒNG TRONG LƯỚI, ĐỪNG TÌM TÊN TRONG CẢ TRANG. Tên người đang đăng nhập nằm sẵn ở
	   thanh bên, nên `strpos($trang,'Chị Trưởng')` xanh kể cả khi lưới rỗng — và bản 4.52.1
	   đúng là như thế: em chặn cả lưới Văn phòng, người ta mất luôn công của chính mình, mà
	   phép thử vẫn báo "vẫn thấy". Anh Thắng: *"phải xem được chính mình chứ"*. */
	teq( '🔴 [' . $cach . '] lưới có ĐÚNG MỘT dòng người — của chính mình', 1,
		substr_count( $trang, 'class="ten-nv"' ) );
	/* 🔴 VÀ DÒNG ẤY PHẢI LÀ MÌNH. Đếm "đúng một" thôi thì một lưới rỗng cũng trượt qua được
	   (0 ≠ 1 nên còn bắt được), nhưng một lưới có đúng một dòng CỦA NGƯỜI KHÁC thì không —
	   mà đó mới là kiểu rò tệ nhất. Đọc thẳng chữ trong ô tên. */
	t( '🔴 [' . $cach . '] và dòng ấy đúng là của mình',
		cq_co_ten( $trang, 'Chị Trưởng' ) || cq_co_ten( $trang, 'CQ_CHT' ),
		cq_ten_hang( $trang ) );
	t( '[' . $cach . '] có băng nói rõ vì sao bảng hẹp',
		false !== mb_strpos( $trang, 'không phải cơ sở anh/chị quản lý' ) );
}

/* ================================================== tháng mình CHƯA chấm lần nào ở đó */

echo "— cơ sở chỉ đi làm, tháng chưa chấm lần nào: vẫn phải thấy hàng của mình —\n";
/* 🔴 ĐÂY LÀ CA BẢN 4.52.3 LÀM TỊT. Anh Thắng ngay sau đó: *"Giờ tịt cả trang cá nhân luôn"* —
   màn POSH_HCM chỉ còn đúng câu "Tháng 2026-09 chưa có dữ liệu chấm công nào ở cơ sở này".
   Vì sao trượt khỏi mọi phép thử trước: bài nào cũng gieo cho người đăng nhập một lượt chấm,
   nên `rows` không bao giờ rỗng và vòng dựng HÀNG TRỐNG không cần chạy. Ở cơ sở mình chỉ đi
   làm, tháng mình chưa bấm lần nào mới là cảnh thật — và 4.52.3 gác cả vòng ấy nên không còn
   hàng nào, mất luôn chỗ bấm ô trống để xin bù.
   ⚠️ CHẠY CẢ HAI CÁCH TÍNH, và đòi cả hai vế một lúc: CÓ hàng của mình, KHÔNG có ai khác. Đòi
      mỗi vế "không lọt ai" thì lưới rỗng cũng xanh — đúng cái bẫy đã sập một lần. */
$TH3 = '2026-10';
foreach ( array( 'gio', 'cong' ) as $cach_t ) {
	$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'cai_dat' ) . " WHERE khoa='CACH_TINH_COSO'" );
	$wpdb->insert( VHCC_DB::t( 'cai_dat' ), array( 'khoa' => 'CACH_TINH_COSO',
		'gia_tri' => wp_json_encode( array( $LAM => $cach_t ) ) ) );
	$_COOKIE = array( VHCC_Web::COOKIE => $tok_cht );
	$_GET    = array( 'man' => 'cham', 'ccs' => $LAM, 'cth' => $TH3 );
	$_POST   = array();
	ob_start();
	VHCC_Web::phuc_vu();
	$trang_t = ob_get_clean();
	$_GET = array();
	$_COOKIE = array();

	t( '🔴 [' . $cach_t . '] tháng chưa chấm: KHÔNG rơi vào câu "chưa có dữ liệu"',
		false === mb_strpos( $trang_t, 'chưa có dữ liệu chấm công' ) );
	teq( '🔴 [' . $cach_t . '] tháng chưa chấm: vẫn đúng MỘT dòng', 1,
		substr_count( $trang_t, 'class="ten-nv"' ) );
	t( '🔴 [' . $cach_t . '] tháng chưa chấm: dòng ấy là của mình',
		cq_co_ten( $trang_t, 'Chị Trưởng' ), cq_ten_hang( $trang_t ) );
	$ro_t = array();
	foreach ( array( 'NGƯỜI KHÁC HẲN', 'CQ_KHAC', 'Em Nhân Viên', 'CQ_NV',
		'NGƯỜI CHƯA CHẤM', 'CQ_TRONG' ) as $x_t ) {
		if ( false !== mb_strpos( $trang_t, $x_t ) ) { $ro_t[] = $x_t; }
	}
	t( '🔴 [' . $cach_t . '] tháng chưa chấm: vẫn KHÔNG lọt ai khác', ! $ro_t, $ro_t );
}

/* Ở cơ sở mình QUẢN thì trang phải hiện đủ — nới không được thành siết nhầm. */
$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'cai_dat' ) . " WHERE khoa='CACH_TINH_COSO'" );
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => $CHINH, 'ngay' => $TH2 . '-03',
	'ma_nv' => 'CQ_KHAC', 'ho_ten' => 'NGƯỜI KHÁC HẲN', 'gio_vao_giay' => 28800,
	'gio_ra_giay' => 61200, 'hau_to' => '', 'nguon' => 'may' ) );
$_COOKIE = array( VHCC_Web::COOKIE => $tok_cht );
$_GET    = array( 'man' => 'cham', 'ccs' => $CHINH, 'cth' => $TH2 );
$_POST   = array();
ob_start();
VHCC_Web::phuc_vu();
$trang_q = ob_get_clean();
$_GET = array();
$_COOKIE = array();
t( '🔴 cơ sở mình QUẢN thì VẪN thấy người khác',
	false !== mb_strpos( $trang_q, 'NGƯỜI KHÁC HẲN' ) );
t( 'và lưới ở đó có NHIỀU HƠN một dòng',
	substr_count( $trang_q, 'class="ten-nv"' ) > 1, substr_count( $trang_q, 'class="ten-nv"' ) );

/* Chiều ngược: ở cơ sở mình QUẢN thì hàng trống VẪN phải dựng — đó là chỗ để bấm bù cho người
   chưa chấm ngày nào. Siết chặt quá là mất luôn việc ấy. */
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'CQ_TRONG2', 'ho_ten' => 'CHƯA CHẤM Ở CHÍNH',
	'vai_tro' => 'Nhân viên', 'cua_hang' => $CHINH, 'trang_thai_lam_viec' => 'Đang làm' ) );
$_COOKIE = array( VHCC_Web::COOKIE => $tok_cht );
$_GET    = array( 'man' => 'cham', 'ccs' => $CHINH, 'cth' => $TH2 );
$_POST   = array();
ob_start();
VHCC_Web::phuc_vu();
$trang_q2 = ob_get_clean();
$_GET = array();
$_COOKIE = array();
t( '🔴 cơ sở mình QUẢN thì hàng trống VẪN dựng (để còn bấm bù)',
	false !== mb_strpos( $trang_q2, 'CHƯA CHẤM Ở CHÍNH' ) );
t( 'và KHÔNG có băng hẹp ở đó',
	false === mb_strpos( $trang_q, 'không phải cơ sở anh/chị quản lý' ) );

echo "\n";
if ( $truot ) {
	echo '🔴 HỎNG ' . count( $truot ) . " phép thử:\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "✓ ĐẠT: $dat phép thử — chấm công ở đâu KHÔNG còn nghĩa là quản được ở đó.\n";
