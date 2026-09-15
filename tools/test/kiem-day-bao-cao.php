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

/* 🔴 CHƯA GHÉP THÌ KHÔNG THẤY GÌ — không được trả '' (nghĩa là "mọi cơ sở"). */
phep( 'chưa ghép cơ sở -> không thấy cơ sở nào', KHH_DT_CHUA_GHEP === khh_dt_phien_co_so() );
khh_dt_dat_ghep( array( 'FZ_ADV_TP' => 'TuTu Train - Aeon Tân Phú' ) );
khh_dt_test_quen_phien();
phep( 'ghép xong thì thấy đúng cơ sở của mình', 'TuTu Train - Aeon Tân Phú' === khh_dt_phien_co_so() );

/* --- vai đọc lại từ bảng, không tin thẻ phiên --- */
khh_dt_day_vao( array( 'ma_nv' => 'NV001', 'ho_ten' => 'Trần Thị B', 'pin' => '4321', 'vai' => 'duyet', 'coso' => 'FZ_ADV_TP' ) );
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
