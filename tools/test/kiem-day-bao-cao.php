<?php
/**
 * ĐẨY NGƯỜI SANG MÀN BÁO CÁO CƠ SỞ — SOI CẢ HAI ĐẦU CỦA CÂY CẦU.
 *
 * Cột "Quản trị báo cáo cơ sở" ở trang Nhân sự gọi sang plugin `khh-doanh-thu` bằng ĐÚNG BA TÊN
 * HÀM, và dò `function_exists()` trước khi gọi. Nên đổi tên một hàm bên ấy KHÔNG làm gì đỏ lên
 * cả: cột chỉ lặng lẽ biến mất khỏi màn Nhân sự, và mãi sau mới có người hỏi *"cột đâu rồi"*.
 * Bài này bắt đúng chuyện đó, cộng với mấy chỗ đã biết là dễ sập:
 *
 *   · PIN trùng  — hai người cùng PIN là hai người cùng một cửa; người cũ phải MẤT PIN và phải
 *                  được kể tên ra, không thì báo cáo của quán này bị nhập bởi người quán kia.
 *   · chưa ghép  — mã cơ sở bên nhân sự chưa khai sang tên cơ sở bên POS thì người ấy phải thấy
 *                  RỖNG, chứ tuyệt đối không phải thấy CẢ 15 QUÁN (ô cơ sở trống nghĩa là "xem
 *                  được hết").
 *   · gỡ người   — gỡ ở trang Nhân sự mà phiên đang mở vẫn vào được thì cái gỡ ấy không có thật.
 *   · 🔴 vai     — (1.58.0, anh Thắng 23/09/2026: *"chỉ đẩy nhân sự qua, chứ không phân quyền
 *                  nhiệm vụ trong đó, mà do trang tự phân quyền"*) đẩy sang KHÔNG mang vai: người
 *                  mới là CHƯA CẤP, không nhập được; vai gửi kèm bị bỏ qua; đẩy lại (bên kia
 *                  `dong_bo()` mỗi lần sửa hồ sơ) KHÔNG xoá vai tab Quản trị đã cấp.
 *
 * Chạy: php tools/test/kiem-day-bao-cao.php
 */

$goc  = dirname( __DIR__, 2 );
$cc   = $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-day-bao-cao.php';
$dt   = $goc . '/wordpress/khh-doanh-thu/nguoi.php';
$dat  = 0;
$hong = array();

function phep( $ten, $dung ) {
	global $dat, $hong;
	if ( $dung ) {
		$dat++;
	} else {
		$hong[] = $ten;
	}
}

/* ================================================================== 1. hai đầu cầu khớp tên */

$ma_cc = file_get_contents( $cc );
$ma_dt = file_get_contents( $dt );
phep( 'đọc được class-vhcc-day-bao-cao.php', false !== $ma_cc );
phep( 'đọc được nguoi.php', false !== $ma_dt );

preg_match_all( "/function_exists\(\s*'(khh_dt_[a-z0-9_]+)'\s*\)/", (string) $ma_cc, $m );
$goi = array_values( array_unique( $m[1] ) );
phep( 'bên chấm công có dò hàm bên báo cáo', count( $goi ) >= 3 );
foreach ( $goi as $ten ) {
	phep( "nguoi.php có hàm $ten()", (bool) preg_match( '/function\s+' . preg_quote( $ten, '/' ) . '\s*\(/', (string) $ma_dt ) );
}

/* Mọi lời gọi khh_dt_* trong lớp đẩy đều phải được gác bằng function_exists CÙNG THÂN HÀM —
   `tools/test/kiem-goi-cheo.php` canh luật này cho các lớp VHCP/VHCC, nhưng hàm trần (không
   thuộc lớp nào) thì nó không soi, nên soi ở đây. */
preg_match_all( '/(?<![a-z_])(khh_dt_[a-z0-9_]+)\s*\(/', (string) $ma_cc, $m2 );
foreach ( array_unique( $m2[1] ) as $ten ) {
	phep( "lời gọi $ten() có gác function_exists", in_array( $ten, $goi, true ) );
}

/* Tên đã lệch thì dừng ngay — phần dưới gọi thẳng mấy hàm ấy, chạy tiếp chỉ nhận một dòng Fatal
   che mất nguyên nhân thật. */
if ( $hong ) {
	echo "\n✗ HAI ĐẦU CẦU KHÔNG KHỚP TÊN HÀM:\n";
	foreach ( $hong as $h ) {
		echo "   · $h\n";
	}
	echo "   Cột \"Quản trị báo cáo cơ sở\" sẽ tự ẩn khỏi trang Nhân sự mà không báo gì.\n";
	exit( 1 );
}

/* ================================================================== 2. chạy thật phần lõi */

require __DIR__ . '/fw/bo-do-khh-dt.php';

khh_dt_test_dung_bang();

/* --- đẩy một người vào --- */
$kq = khh_dt_day_vao( array(
	'ma_nv'  => 'nv001',
	'ho_ten' => 'Trần Thị B',
	'pin'    => '4321',
	'vai'    => 'nhap',
	'coso'   => 'FZ_ADV_TP',
) );
phep( 'đẩy người mới vào được', ! empty( $kq['ok'] ) && 'them' === $kq['viec'] );
phep( 'mã NV ghi hoa', khh_dt_da_day( 'NV001' ) && khh_dt_da_day( 'nv001' ) );
phep( 'báo là chưa ghép cơ sở', ! empty( $kq['chua_ghep'] ) );
/* 🔴 Gửi kèm vai 'nhap' mà người mới vẫn phải là CHƯA CẤP. */
phep( '🔴 đẩy sang không mang vai: người mới là chưa cấp', '' === (string) khh_dt_nguoi( 'NV001' )['vai'] );
phep( 'và kết quả đẩy nói rõ chưa cấp', ! empty( $kq['chua_cap'] ) && '' === $kq['vai'] );
$kq = khh_dt_day_vao( array( 'ma_nv' => 'NV009', 'ho_ten' => 'Thử Duyệt', 'pin' => '90909', 'vai' => 'duyet', 'coso' => 'FZ_ADV_TP' ) );
phep( '🔴 gửi kèm vai "duyet" cũng bị bỏ qua', ! empty( $kq['ok'] ) && '' === (string) khh_dt_nguoi( 'NV009' )['vai'] );
khh_dt_day_ra( 'NV009' );

/* --- PIN phải là 4–8 số --- */
$kq = khh_dt_day_vao( array( 'ma_nv' => 'NV002', 'ho_ten' => 'C', 'pin' => '12', 'coso' => 'FZ_ADV_TP' ) );
phep( 'PIN 2 số bị chối', empty( $kq['ok'] ) );
$kq = khh_dt_day_vao( array( 'ma_nv' => 'NV002', 'ho_ten' => 'C', 'pin' => '12345678', 'coso' => 'FZ_ADV_TP' ) );
phep( 'PIN 8 số nhận được', ! empty( $kq['ok'] ) );

/* --- đăng nhập bằng PIN --- */
$dn = khh_dt_pin_dang_nhap( '4321' );
phep( 'gõ đúng PIN thì vào được', ! empty( $dn['ok'] ) && ! empty( $dn['token'] ) );
khh_dt_test_dat_the( $dn['token'] );
$ai = khh_dt_phien_nguoi();
phep( 'phiên nhận ra đúng người', $ai && 'NV001' === $ai['ma_nv'] );
phep( 'gõ sai PIN thì không vào', empty( khh_dt_pin_dang_nhap( '9999' )['ok'] ) );

/* --- 🔴 vai do TAB QUẢN TRỊ cấp (`khh_dt_dat_vai`), không phải trang nhân sự --- */
$kq = khh_dt_dat_vai( 'NV001', 'quan_tri' );
phep( 'vai lạ bị chối, không quy về "nhap"', empty( $kq['ok'] ) && '' === (string) khh_dt_nguoi( 'NV001' )['vai'] );
$kq = khh_dt_dat_vai( 'NV404', 'nhap' );
phep( 'cấp cho mã chưa đẩy sang thì chối', empty( $kq['ok'] ) );
$kq = khh_dt_dat_vai( 'nv001', 'nhap' );
phep( 'cấp vai "nhap" được (mã thường cũng nhận)', ! empty( $kq['ok'] ) && 'nhap' === (string) khh_dt_nguoi( 'NV001' )['vai'] );
khh_dt_test_quen_phien();
phep( 'phiên đang mở thấy vai mới ngay', 'nhap' === (string) khh_dt_phien_nguoi()['vai'] );
/* 🔴 Bên chấm công `dong_bo()` đẩy lại sau mỗi lần sửa hồ sơ — vai vừa cấp KHÔNG được bay. */
$kq = khh_dt_day_vao( array( 'ma_nv' => 'NV001', 'ho_ten' => 'Trần Thị B', 'pin' => '4321', 'vai' => 'duyet', 'coso' => 'FZ_ADV_TP' ) );
phep( '🔴 đẩy lại (kèm vai khác) không đổi vai đã cấp', ! empty( $kq['ok'] ) && 'sua' === $kq['viec'] && 'nhap' === (string) khh_dt_nguoi( 'NV001' )['vai'] );
phep( 'và kết quả đẩy lại báo không còn chưa cấp', empty( $kq['chua_cap'] ) && 'nhap' === $kq['vai'] );
$kq = khh_dt_dat_vai( 'NV001', '' );
phep( 'thu vai về "" được', ! empty( $kq['ok'] ) && '' === (string) khh_dt_nguoi( 'NV001' )['vai'] );
khh_dt_day_vao( array( 'ma_nv' => 'NV001', 'ho_ten' => 'Trần Thị B', 'pin' => '4321', 'coso' => 'FZ_ADV_TP' ) );
phep( 'đẩy lại người chưa cấp vẫn là chưa cấp', '' === (string) khh_dt_nguoi( 'NV001' )['vai'] );
khh_dt_dat_vai( 'NV001', 'nhap' );
khh_dt_test_quen_phien();

/* 🔴 CHƯA GHÉP THÌ KHÔNG THẤY GÌ — không được trả mảng rỗng (nghĩa là "mọi cơ sở"). */
phep( 'chưa ghép cơ sở -> không thấy cơ sở nào', array( KHH_DT_CHUA_GHEP ) === khh_dt_phien_co_so_ds() );
khh_dt_dat_ghep( array( 'FZ_ADV_TP' => 'TuTu Train - Aeon Tân Phú' ) );
khh_dt_test_quen_phien();
phep( 'ghép xong thì thấy đúng cơ sở của mình', array( 'TuTu Train - Aeon Tân Phú' ) === khh_dt_phien_co_so_ds() );
/* Hình dạng CŨ (bản 1.6.0 lưu mỗi mã một chuỗi) phải đọc được nguyên — site đã khai vài mã rồi. */
phep( 'đọc được bảng ghép kiểu cũ (một chuỗi)', array( 'TuTu Train - Aeon Tân Phú' ) === khh_dt_ghep_ten_ds( 'FZ_ADV_TP' ) );

/* ---- 🔴 MỘT MÃ, HAI QUÁN TRÊN MÁY POS (anh Thắng 15/09/2026) ----
   Gò An Lạc: sổ nhân sự một mã, máy POS hai quán, một cửa hàng trưởng coi cả hai. */
khh_dt_dat_ghep( array( 'FZ_ADV_TP' => array( 'FUNZONE ADVENTURE GO AN LẠC', 'COFFE GO AN LẠC' ) ) );
khh_dt_test_quen_phien();
phep( 'một mã tích được hai quán POS',
	array( 'FUNZONE ADVENTURE GO AN LẠC', 'COFFE GO AN LẠC' ) === khh_dt_phien_co_so_ds() );
phep( 'và mã ấy hết bị đòi ghép', ! in_array( 'FZ_ADV_TP', khh_dt_ma_chua_ghep(), true ) );
khh_dt_dat_ghep( array( 'FZ_ADV_TP' => array() ) );
khh_dt_test_quen_phien();
phep( 'bỏ tích hết thì lại là chưa ghép', in_array( 'FZ_ADV_TP', khh_dt_ma_chua_ghep(), true ) );
khh_dt_dat_ghep( array( 'FZ_ADV_TP' => 'TuTu Train - Aeon Tân Phú' ) );
khh_dt_test_quen_phien();

/* ---- một người HAI cơ sở (anh Thắng 15/09/2026) ---- */
$kq = khh_dt_day_vao( array(
	'ma_nv'  => 'NV001',
	'ho_ten' => 'Trần Thị B',
	'pin'    => '4321',
	'vai'    => 'nhap',
	'coso'   => 'FZ_ADV_TP, TUTU_TP',
) );
khh_dt_test_quen_phien();
phep( 'ghép mới một trong hai cơ sở -> vẫn báo chưa xong', ! empty( $kq['chua_ghep'] ) );
phep( 'và tạm thời chỉ thấy cơ sở đã ghép', array( 'TuTu Train - Aeon Tân Phú' ) === khh_dt_phien_co_so_ds() );
khh_dt_dat_ghep( array(
	'FZ_ADV_TP' => array( 'TuTu Train - Aeon Tân Phú', 'COFFE GO AN LẠC' ),
	'TUTU_TP'   => 'TuTu Train - Tân Phú',
) );
khh_dt_test_quen_phien();
phep( 'hai mã, một mã hai quán -> gộp đủ cả ba',
	array( 'TuTu Train - Aeon Tân Phú', 'COFFE GO AN LẠC', 'TuTu Train - Tân Phú' ) === khh_dt_phien_co_so_ds() );
$kq = khh_dt_day_vao( array( 'ma_nv' => 'NV001', 'ho_ten' => 'Trần Thị B', 'pin' => '4321', 'coso' => 'FZ_ADV_TP, TUTU_TP' ) );
phep( 'ghép đủ rồi thì hết báo chưa ghép', empty( $kq['chua_ghep'] ) );

/* ---- 🔴 KẾ TOÁN XEM TỔNG: vai 'duyet' KHÔNG bị bó vào cơ sở của mình ----
   VP_KH-HCM không phải một quán nào cả nên không bao giờ ghép được; bó theo cơ sở thì đúng người
   cần nhìn cả 15 quán lại là người thấy rỗng. */
khh_dt_day_vao( array(
	'ma_nv'  => 'KT01',
	'ho_ten' => 'Lý Tiểu Phương',
	'pin'    => '778899',
	'coso'   => 'VP_KH-HCM',
) );
$dn_kt = khh_dt_pin_dang_nhap( '778899' );
khh_dt_test_dat_the( $dn_kt['token'] );
/* Chưa cấp vai thì kế toán CHƯA xem tổng: mã VP không ghép được nên thấy rỗng — và mã ấy còn bị
   nhắc ghép, vì lúc này chưa ai biết đó là người duyệt. */
phep( 'chưa cấp vai -> kế toán chưa xem tổng (thấy rỗng)', array( KHH_DT_CHUA_GHEP ) === khh_dt_phien_co_so_ds() );
khh_dt_dat_vai( 'KT01', 'duyet' );
khh_dt_test_quen_phien();
phep( 'kế toán vai duyệt xem được tổng mọi cơ sở', array() === khh_dt_phien_co_so_ds() );
phep( 'và mã văn phòng không bị đòi ghép', ! in_array( 'VP_KH-HCM', khh_dt_ma_chua_ghep(), true ) );
/* Sổ cho tab Quản trị: có đủ người, có vai, KHÔNG lộ PIN. */
$so = khh_dt_ds_nguoi_pin();
$kt = array_values( array_filter( $so, function ( $x ) { return 'KT01' === $x['ma_nv']; } ) );
phep( 'sổ PIN cho tab Quản trị có kế toán với vai duyệt', $kt && 'duyet' === $kt[0]['vai'] && array( 'VP_KH-HCM' ) === $kt[0]['coso_ds'] );
phep( 'và không lộ PIN', $kt && ! isset( $kt[0]['pin'] ) && true === $kt[0]['co_pin'] );
$b = array_values( array_filter( $so, function ( $x ) { return 'NV001' === $x['ma_nv']; } ) );
phep( 'sổ kèm tên POS đã ghép của từng người', $b && in_array( 'TuTu Train - Aeon Tân Phú', $b[0]['coso_ten'], true ) );

/* Về lại phiên của cửa hàng trưởng cho các phép sau. */
$dn = khh_dt_pin_dang_nhap( '4321' );
khh_dt_test_dat_the( $dn['token'] );

/* --- vai đọc lại từ bảng, không tin thẻ phiên --- */
khh_dt_dat_vai( 'NV001', 'duyet' );
khh_dt_test_quen_phien();
$ai = khh_dt_phien_nguoi();
phep( 'đổi vai là phiên đang mở thấy ngay', $ai && 'duyet' === $ai['vai'] );

/* --- PIN trùng: người cũ mất PIN, và phải được kể tên --- */
$kq = khh_dt_day_vao( array( 'ma_nv' => 'NV003', 'ho_ten' => 'Lê Văn D', 'pin' => '4321', 'coso' => 'FZ_ADV_TP' ) );
phep( 'PIN trùng vẫn đẩy được người mới', ! empty( $kq['ok'] ) );
phep( 'và kể tên người vừa mất PIN', ! empty( $kq['mat_pin'] ) && false !== strpos( $kq['mat_pin'][0], 'NV001' ) );
phep( 'người cũ hết PIN', '' === (string) khh_dt_nguoi( 'NV001' )['pin'] );
khh_dt_test_quen_phien();
phep( 'và phiên cũ của họ bị đóng', null === khh_dt_phien_nguoi() );
$dn = khh_dt_pin_dang_nhap( '4321' );
phep( 'PIN ấy nay là của người mới', ! empty( $dn['ok'] ) );
khh_dt_test_dat_the( $dn['token'] );
khh_dt_test_quen_phien();
phep( 'đúng người mới', khh_dt_phien_nguoi()['ma_nv'] === 'NV003' );

/* --- gỡ người: hàng mất, phiên đang mở chết theo --- */
khh_dt_day_ra( 'NV003' );
khh_dt_test_quen_phien();
phep( 'gỡ xong thì không còn trong sổ', ! khh_dt_da_day( 'NV003' ) );
phep( 'và phiên đang mở hết hiệu lực ngay', null === khh_dt_phien_nguoi() );

/* ================================================================== kết */

if ( $hong ) {
	echo "\n✗ HỎNG " . count( $hong ) . " phép:\n";
	foreach ( $hong as $h ) {
		echo "   · $h\n";
	}
	exit( 1 );
}
echo "\n✓ SẠCH — $dat phép cầu đẩy người sang màn báo cáo cơ sở\n";
