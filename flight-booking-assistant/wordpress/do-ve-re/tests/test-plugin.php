<?php
/**
 * Chạy: php tests/test-plugin.php
 * Test phần logic thuần PHP: tính phí, mã đơn, khớp tiền về, kiểm tra dữ liệu,
 * đổi dữ liệu Amadeus và soạn thư. Không cần cài WordPress, không chạm mạng.
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

require __DIR__ . '/stub-wordpress.php';

// nạp plugin nhưng bỏ qua phần cần WordPress thật
$goc = dirname( __DIR__ );
$src = file_get_contents( $goc . '/do-ve-re.php' );
$src = preg_replace( '/register_activation_hook.*?\n\n/s', '', $src );
$src = preg_replace( '/add_action\(\s*\'plugins_loaded\'.*?\}\s*\);\n/s', '', $src );
$src = str_replace( "require_once DVR_DIR . 'includes/class-dvr-rest.php';\n", '', $src );
$src = str_replace( "if ( ! defined( 'ABSPATH' ) ) {\n\texit;\n}", '', $src );
$src = preg_replace( '/^<\?php/', '', $src );
$src = str_replace( "plugin_dir_path( __FILE__ )", "'" . $goc . "/'", $src );
$src = str_replace( "plugin_dir_url( __FILE__ )", "'https://ve.knh.vn/wp-content/plugins/do-ve-re/'", $src );
eval( $src );

$dat = 0;
$hong = 0;
function ok( $ten, $dung, $them = null ) {
	global $dat, $hong;
	if ( $dung ) {
		$dat++;
		echo "  ✓ $ten\n";
	} else {
		$hong++;
		echo "  ✗ $ten" . ( null !== $them ? '  → ' . json_encode( $them, JSON_UNESCAPED_UNICODE ) : '' ) . "\n";
	}
}

$GLOBALS['dvr_options']['dovere_settings'] = array(
	'shop_name' => 'Vé K&H', 'bank_id' => '970436', 'bank_account' => '0071000123456',
	'bank_name' => 'CONG TY TNHH K&H', 'bank_label' => 'Vietcombank',
	'fee_pct' => 3, 'fee_flat' => 0, 'fee_min' => 50000, 'hold_minutes' => 30, 'order_page' => 12,
);

echo "\nPhí dịch vụ\n";
ok( '3% của 1.690.000 nhưng không dưới 50.000', DVR_Store::tinh_phi( 1690000 ) === 50700, DVR_Store::tinh_phi( 1690000 ) );
ok( 'vé rẻ thì lấy mức tối thiểu', DVR_Store::tinh_phi( 500000 ) === 50000, DVR_Store::tinh_phi( 500000 ) );
ok( 'khai 0% và không tối thiểu thì miễn phí',
	DVR_Store::tinh_phi( 1000000, array( 'fee_pct' => 0, 'fee_flat' => 0, 'fee_min' => 0 ) ) === 0 );

echo "\nMã đơn\n";
$m1 = DVR_Store::ma_don();
$m2 = DVR_Store::ma_don();
ok( 'đúng dạng DVR + 8 số', (bool) preg_match( '/^DVR\d{8}$/', $m1 ), $m1 );
ok( 'không trùng nhau', $m1 !== $m2, array( $m1, $m2 ) );

echo "\nKhớp tiền về theo nội dung chuyển khoản\n";
ok( 'nội dung sạch', DVR_Store::khop_ma( 'DVR26090001' ) === 'DVR26090001' );
ok( 'ngân hàng chèn thêm chữ', DVR_Store::khop_ma( 'CT tu NGUYEN VAN A den DVR26090001 ve may bay' ) === 'DVR26090001' );
ok( 'có dấu cách chen giữa', DVR_Store::khop_ma( 'DVR 2609 0001' ) === 'DVR26090001' );
ok( 'không có mã thì trả null', null === DVR_Store::khop_ma( 'chuyen tien an trua' ) );

echo "\nKiểm tra dữ liệu khách gửi lên\n";
$don_tot = array(
	'flight'  => array( 'route' => 'SGN-HAN', 'date' => '2026-10-04', 'airline' => 'VJ', 'number' => 'VJ120', 'dep' => '06:15', 'arr' => '08:25' ),
	'fare'    => array( 'total' => 1690000, 'cur' => 'VND' ),
	'pax'     => array( array( 'full' => 'NGUYEN VAN A' ) ),
	'contact' => array( 'name' => 'Nguyễn Văn A', 'phone' => '0912345678', 'email' => 'vana@congty.com' ),
);
list( $loi, $pax ) = DVR_Store::kiem_tra( $don_tot );
ok( 'đơn đúng thì không lỗi', array() === $loi, $loi );
ok( 'giữ lại danh sách khách', count( $pax ) === 1 );

$xau = $don_tot;
$xau['contact']['phone'] = '123';
list( $loi ) = DVR_Store::kiem_tra( $xau );
ok( 'số điện thoại sai bị chặn', (bool) preg_grep( '/điện thoại/u', $loi ), $loi );

$xau = $don_tot;
$xau['pax'] = array( array( 'full' => 'A' ) );
list( $loi ) = DVR_Store::kiem_tra( $xau );
ok( 'tên thiếu họ bị chặn', (bool) preg_grep( '/họ và tên/u', $loi ), $loi );

$xau = $don_tot;
$xau['fare'] = array( 'total' => 120, 'cur' => 'EUR' );
list( $loi ) = DVR_Store::kiem_tra( $xau );
ok( 'đơn không phải VND bị chặn', (bool) preg_grep( '/VND/', $loi ), $loi );

$xau = $don_tot;
$xau['contact']['email'] = 'khong-phai-email';
list( $loi ) = DVR_Store::kiem_tra( $xau );
ok( 'email sai bị chặn', (bool) preg_grep( '/email/u', $loi ), $loi );

echo "\nĐổi dữ liệu Amadeus\n";
$body = json_decode( file_get_contents( $goc . '/tests/amadeus-offers.json' ), true );
$ch   = DVR_Amadeus::doi_du_lieu( $body, array( 'cabin' => 'ECONOMY' ) );
ok( 'đọc được cả ba chào giá', count( $ch ) === 3, count( $ch ) );
ok( 'xếp từ rẻ tới đắt', $ch[0]['price'] <= $ch[1]['price'] && $ch[1]['price'] <= $ch[2]['price'], array_column( $ch, 'price' ) );
ok( 'rẻ nhất là QH 1.450.000', 1450000 === $ch[0]['price'] && 'QH' === $ch[0]['al']['code'], $ch[0]['price'] );
ok( 'tên hãng lấy từ dictionaries', 'Bamboo Airways' === $ch[0]['al']['name'], $ch[0]['al']['name'] );
ok( 'đếm đúng điểm dừng', 1 === $ch[0]['stops'] && 0 === $ch[1]['stops'], array_column( $ch, 'stops' ) );
ok( 'giờ bay lấy từ segment', '06:15' === $ch[1]['dep'] && '08:25' === $ch[1]['arr'], array( $ch[1]['dep'], $ch[1]['arr'] ) );
ok( 'nhận ra chuyến qua đêm', true === $ch[0]['overnight'] );
ok( 'đọc hành lý ký gửi', true === $ch[0]['bag'] && false === $ch[1]['bag'] );
ok( 'PT2H10M đổi ra 130 phút', 130 === DVR_Amadeus::phut( 'PT2H10M' ), DVR_Amadeus::phut( 'PT2H10M' ) );
ok( 'PT45M đổi ra 45 phút', 45 === DVR_Amadeus::phut( 'PT45M' ) );

echo "\nMã QR chuyển khoản\n";
$qr = dvr_qr( 1740700, 'DVR26090001' );
ok( 'đủ số tài khoản, số tiền và mã đơn',
	strpos( $qr, '970436-0071000123456' ) !== false && strpos( $qr, 'amount=1740700' ) !== false && strpos( $qr, 'addInfo=DVR26090001' ) !== false, $qr );

echo "\nSoạn thư\n";
$o = array(
	'code' => 'DVR26090001', 'expiresAt' => gmdate( 'Y-m-d H:i:s', time() + 1800 ), 'pnr' => 'ABC123',
	'status' => 'da_xuat_ve', 'statusText' => 'Đã xuất vé',
	'flight' => array( 'route' => 'SGN-HAN', 'date' => '2026-10-04', 'airline' => 'VJ', 'number' => 'VJ120', 'dep' => '06:15', 'arr' => '08:25' ),
	'money'  => array( 'fare' => 1690000, 'fee' => 50700, 'total' => 1740700, 'paid' => 1740700 ),
	'pax'    => array( array( 'full' => 'NGUYỄN VĂN A' ) ),
	'contact' => array( 'email' => 'vana@congty.com' ),
);
$t = DVR_Mail::soan( 'moi', $o );
ok( 'thư hướng dẫn có mã đơn ở tiêu đề', strpos( $t['subject'], 'DVR26090001' ) !== false, $t['subject'] );
ok( 'thư hướng dẫn có số tài khoản và số tiền',
	strpos( $t['html'], '0071000123456' ) !== false && strpos( $t['html'], '1.740.700đ' ) !== false );
$t = DVR_Mail::soan( 'da_nhan_tien', $o );
ok( 'thư nhận tiền không báo thiếu khi đã đủ', strpos( $t['html'], 'Còn thiếu' ) === false );
$o2 = $o;
$o2['money']['paid'] = 1000000;
$t = DVR_Mail::soan( 'da_nhan_tien', $o2 );
ok( 'chuyển thiếu thì thư nói rõ còn thiếu bao nhiêu', strpos( $t['html'], '740.700đ' ) !== false, $t['html'] );
$t = DVR_Mail::soan( 'da_xuat_ve', $o );
ok( 'thư xuất vé có mã đặt chỗ ở tiêu đề', strpos( $t['subject'], 'ABC123' ) !== false, $t['subject'] );
ok( 'thư xuất vé có tên khách có dấu', strpos( $t['html'], 'NGUYỄN VĂN A' ) !== false );
ok( 'thư xuất vé kèm link tra đơn', strpos( $t['html'], 'don=DVR26090001' ) !== false );
$t = DVR_Mail::soan( 'hoan_tien', $o );
ok( 'thư hoàn tiền có số tiền hoàn', strpos( $t['html'], '1.740.700đ' ) !== false );
ok( 'mẫu thư lạ thì trả null', null === DVR_Mail::soan( 'khong-co', $o ) );

echo "\nTự tạo trang cho khách\n";
$GLOBALS['dvr_options']['dovere_settings']['order_page'] = 0;
$t1 = DVR_Admin::tao_trang();
ok( 'tạo đủ hai trang', count( $t1 ) === 2 && $t1['bang_gia'] && $t1['dat_ve'], $t1 );
ok( 'trang bảng giá chứa shortcode', strpos( $GLOBALS['dvr_posts'][ $t1['bang_gia'] ]['post_content'], '[do_ve_re]' ) !== false );
ok( 'trang có đường dẫn đẹp', $GLOBALS['dvr_posts'][ $t1['bang_gia'] ]['post_name'] === 've-may-bay-gia-re', $GLOBALS['dvr_posts'][ $t1['bang_gia'] ]['post_name'] );
ok( 'trang đặt vé chứa shortcode', strpos( $GLOBALS['dvr_posts'][ $t1['dat_ve'] ]['post_content'], '[do_ve_re_dat_ve]' ) !== false );
ok( 'tự khai luôn trang đặt vé vào cài đặt', (int) dvr_cai_dat( 'order_page' ) === (int) $t1['dat_ve'], dvr_cai_dat( 'order_page' ) );

$t2 = DVR_Admin::tao_trang();
ok( 'gọi lại không tạo trùng', $t2 == $t1 && count( $GLOBALS['dvr_posts'] ) === 2, count( $GLOBALS['dvr_posts'] ) );

$GLOBALS['dvr_posts'][ $t1['dat_ve'] ]['post_status'] = 'trash';
$t3 = DVR_Admin::tao_trang();
ok( 'trang bị xoá thì dựng lại', $t3['dat_ve'] !== $t1['dat_ve'] && $t3['bang_gia'] === $t1['bang_gia'], array( $t1, $t3 ) );

// tự dựng: chạy một lần cho mỗi phiên bản, không dựng lại sau lưng người dùng
$GLOBALS['dvr_options']['dovere_pages'] = array();
$GLOBALS['dvr_options']['dovere_tu_dung'] = '';
$truoc = count( $GLOBALS['dvr_posts'] );
DVR_Admin::tu_dung_trang();
ok( 'vào quản trị là dựng sẵn trang, không phải bấm gì', count( $GLOBALS['dvr_posts'] ) >= $truoc, count( $GLOBALS['dvr_posts'] ) );
ok( 'ghi nhớ đã dựng cho phiên bản này', get_option( 'dovere_tu_dung' ) === DVR_VERSION, get_option( 'dovere_tu_dung' ) );
$sau = count( $GLOBALS['dvr_posts'] );
$GLOBALS['dvr_options']['dovere_pages'] = array();
DVR_Admin::tu_dung_trang();
ok( 'lần vào sau không dựng lại nữa', count( $GLOBALS['dvr_posts'] ) === $sau, count( $GLOBALS['dvr_posts'] ) );

$GLOBALS['dvr_options']['dovere_pages'] = $t3;
$link = DVR_Admin::trang_khach();
ok( 'đưa ra được link cho khách',
	strpos( $link['bang_gia'], 'https://ve.knh.vn/' ) === 0 && strpos( $link['dat_ve'], 'https://ve.knh.vn/' ) === 0, $link );

echo "\nChân trang thông tin công ty\n";
$GLOBALS['dvr_options']['dovere_settings'] = array_merge( $GLOBALS['dvr_options']['dovere_settings'], array(
	'cty_vi' => 'CÔNG TY TNHH DỊCH VỤ VÀ GIẢI TRÍ K&H',
	'cty_en' => 'K&H SERVICES AND ENTERTAINMENT COMPANY LIMITED',
	'cty_mst' => '0106924989', 'cty_dai_dien' => 'Nguyễn Văn Kiên', 'cty_tu_ngay' => '05/08/2015',
	'cty_dia_chi' => 'Thôn Mai Nội, Xã Sóc Sơn, Thành phố Hà Nội, Việt Nam',
	'cty_dien_thoai' => '0435961469', 'cty_co_quan' => 'Thuế cơ sở 18 thành phố Hà Nội',
	'cty_chi_nhanh' => 'Đà Nẵng, Hải Phòng, Bình Dương',
) );
$ct = DVR_Shortcodes::chan_trang();
ok( 'có tên công ty và mã số thuế', strpos( $ct, '0106924989' ) !== false && strpos( $ct, 'GIẢI TRÍ K&amp;H' ) !== false );
ok( 'ký tự & được thoát, không vỡ HTML', strpos( $ct, 'K&H SERVICES' ) === false && strpos( $ct, 'K&amp;H SERVICES' ) !== false );
ok( 'có địa chỉ, điện thoại, cơ quan thuế',
	strpos( $ct, 'Sóc Sơn' ) !== false && strpos( $ct, '0435961469' ) !== false && strpos( $ct, 'Thuế cơ sở 18' ) !== false );
ok( 'chi nhánh tách bằng dấu chấm giữa', strpos( $ct, 'Đà Nẵng · Hải Phòng · Bình Dương' ) !== false, $ct );
ok( 'có dòng bản quyền kèm năm', strpos( $ct, '© ' . gmdate( 'Y' ) ) !== false );
$GLOBALS['dvr_options']['dovere_settings']['cty_vi'] = '';
ok( 'xoá tên công ty là bỏ hẳn khối', '' === DVR_Shortcodes::chan_trang() );

echo "\nNguồn giá Duffel\n";
$yc = DVR_Duffel::goi_yeu_cau( array( 'from' => 'SGN', 'to' => 'HAN', 'dep' => '2026-10-04', 'ret' => '',
	'adt' => 2, 'chd' => 1, 'inf' => 0, 'cabin' => 'ECONOMY' ) );
ok( 'một chiều thì gửi đúng một chặng', count( $yc['data']['slices'] ) === 1, $yc['data']['slices'] );
ok( 'đúng điểm đi, điểm đến, ngày',
	'SGN' === $yc['data']['slices'][0]['origin'] && 'HAN' === $yc['data']['slices'][0]['destination']
	&& '2026-10-04' === $yc['data']['slices'][0]['departure_date'], $yc['data']['slices'][0] );
ok( '2 người lớn + 1 trẻ em thành 3 hành khách', count( $yc['data']['passengers'] ) === 3, $yc['data']['passengers'] );
ok( 'trẻ em gửi bằng tuổi, không phải type', isset( $yc['data']['passengers'][2]['age'] ), $yc['data']['passengers'][2] );
ok( 'hạng vé đổi sang chữ thường của Duffel', 'economy' === $yc['data']['cabin_class'], $yc['data'] );
$yc2 = DVR_Duffel::goi_yeu_cau( array( 'from' => 'SGN', 'to' => 'HAN', 'dep' => '2026-10-04', 'ret' => '2026-10-08', 'adt' => 1, 'cabin' => 'BUSINESS' ) );
ok( 'khứ hồi thì gửi hai chặng, chặng về ngược chiều',
	count( $yc2['data']['slices'] ) === 2 && 'HAN' === $yc2['data']['slices'][1]['origin'], $yc2['data']['slices'] );
ok( 'thương gia đổi đúng tên', 'business' === $yc2['data']['cabin_class'] );

$body = json_decode( file_get_contents( $goc . '/tests/duffel-offers.json' ), true );
$ch   = DVR_Duffel::doi_du_lieu( $body, array( 'adt' => 2, 'chd' => 0, 'cabin' => 'ECONOMY' ) );
ok( 'đọc được cả hai chào giá', count( $ch ) === 2, count( $ch ) );
ok( 'xếp từ rẻ tới đắt', $ch[0]['price'] <= $ch[1]['price'], array_column( $ch, 'price' ) );
ok( 'chia giá theo đầu khách', 1130000 === $ch[0]['price'] && 2260000 === $ch[0]['total'], array( $ch[0]['price'], $ch[0]['total'] ) );
ok( 'lấy tên hãng từ marketing_carrier', 'Bamboo Airways' === $ch[0]['al']['name'], $ch[0]['al'] );
ok( 'đếm đúng điểm dừng', 1 === $ch[0]['stops'] && 0 === $ch[1]['stops'], array_column( $ch, 'stops' ) );
ok( 'giờ bay cắt từ chuỗi ISO', '12:40' === $ch[1]['dep'] && '14:50' === $ch[1]['arr'], array( $ch[1]['dep'], $ch[1]['arr'] ) );
ok( 'nhận ra chuyến qua đêm', true === $ch[0]['overnight'] );
ok( 'đọc hành lý ký gửi', true === $ch[1]['bag'] && false === $ch[0]['bag'], array_column( $ch, 'bag' ) );
ok( 'giữ nguyên tiền tệ', 'VND' === $ch[0]['cur'] );
ok( 'số hiệu chuyến ghép từ mã hãng', 'QH130' === $ch[0]['code'], $ch[0]['code'] );

echo "\nMàu thương hiệu\n";
ok( 'đọc được mã 6 ký tự', dvr_mau_rgb( '#C1960C' ) === array( 193, 150, 12 ), dvr_mau_rgb( '#C1960C' ) );
ok( 'đọc được mã rút gọn 3 ký tự', dvr_mau_rgb( '#fc0' ) === array( 255, 204, 0 ), dvr_mau_rgb( '#fc0' ) );
ok( 'mã sai thì trả null', null === dvr_mau_rgb( 'vàng' ) );
ok( 'vàng K&H: chữ trắng đọc không nổi nên phải chọn chữ đen', '#152242' === dvr_chu_tren_nen( '#C1960C' ), dvr_chu_tren_nen( '#C1960C' ) );
ok( 'cam mặc định: chọn chữ trắng', '#FFFFFF' === dvr_chu_tren_nen( '#D9432B' ), dvr_chu_tren_nen( '#D9432B' ) );
ok( 'chữ chọn ra đạt tương phản 4.5 trở lên',
	dvr_tuong_phan( '#C1960C', dvr_chu_tren_nen( '#C1960C' ) ) >= 4.5, round( dvr_tuong_phan( '#C1960C', '#152242' ), 2 ) );
ok( 'chữ trắng trên vàng thì KHÔNG đạt — đúng như mắt thấy',
	dvr_tuong_phan( '#C1960C', '#FFFFFF' ) < 3, round( dvr_tuong_phan( '#C1960C', '#FFFFFF' ), 2 ) );
ok( 'trộn trắng ra sắc nhạt hơn', dvr_lam_nhat( '#C1960C', 0.5 ) === '#E0CB86', dvr_lam_nhat( '#C1960C', 0.5 ) );

$GLOBALS['dvr_options']['dovere_settings']['mau_chinh'] = '#C1960C';
ob_start(); DVR_Shortcodes::mau_thuong_hieu(); $css = ob_get_clean();
ok( 'in ra biến CSS đúng màu', strpos( $css, '--accent:#C1960C' ) !== false, $css );
ok( 'chữ trên nút chuyển sang đen cho dễ đọc', strpos( $css, '--on-accent:#152242' ) !== false, $css );
$GLOBALS['dvr_options']['dovere_settings']['mau_chinh'] = '';
ob_start(); DVR_Shortcodes::mau_thuong_hieu(); $trong = ob_get_clean();
ok( 'không khai màu thì không in gì', '' === $trong );

echo "\n$dat đạt, $hong hỏng\n";
exit( $hong ? 1 : 0 );
