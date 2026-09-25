<?php
/**
 * MÁY CHỦ XEM TRƯỚC CHO TRANG TRẠM — chạy bằng máy chủ sẵn có của PHP.
 *
 * =============================================================================================
 * 🔴 VÌ SAO KHÔNG DỰNG RA MỘT TỆP HTML RỒI CHỤP NHƯ MÀN NHÂN SỰ
 * =============================================================================================
 * Màn nhân sự dựng xong là xong: máy chủ in ra cả cái bảng. Trạm thì KHÔNG — nó in ra một cái
 * vỏ rỗng, rồi JavaScript gọi `?viec=toi`, `?viec=chuong`, `?viec=ung` mới có nội dung. Mở tệp
 * `file://` thì mọi lời gọi ấy trượt, và ảnh chụp được là một trang trắng có năm cái tab.
 *
 * Tệ hơn: thẻ phiên của trạm nằm trong `localStorage` chứ không phải cookie (xem chú thích ở
 * `VHCC_Tram`), nên KHÔNG có cách nào "đăng nhập sẵn" rồi dựng ra HTML tĩnh. Phải đăng nhập
 * bằng đúng đường của trình duyệt thật: gõ PIN, nhận thẻ, cất vào `localStorage`.
 *
 * Nên chỗ này dựng một máy chủ thật, nhỏ. Được thêm một thứ đáng giá hơn cả ảnh: mọi lời gọi
 * đều đi qua ĐÚNG mã của cổng (`VHCC_Tram::cong()`), nên nếu một cửa nào hỏng thì nó hỏng ở đây
 * chứ không hỏng trên máy nhân viên.
 *
 * ⚠️ CHỈ ĐỂ XEM TRƯỚC Ở MÁY MÌNH. Nó chạy trên bệ đỡ WordPress giả và một CSDL SQLite trong thư
 *    mục tạm, KHÔNG có bảo mật thật. Đừng bao giờ mở ra mạng.
 *
 * Chạy: bash tools/xem/xem-tram.sh
 */

$goc = dirname( dirname( __DIR__ ) );

/* Tệp tĩnh (nếu có) — để máy chủ sẵn có của PHP tự phục vụ. */
$duong = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
if ( '' !== $duong && file_exists( __DIR__ . $duong ) && ! is_dir( __DIR__ . $duong ) ) {
	return false;
}

require_once $goc . '/tools/test/wp-stub.php';
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );
vhcc_test_boot( $goc . '/wordpress/vhcp-cham-cong' );
global $wpdb;

/* GIEO MỘT LẦN. Sổ nằm trong tệp nên nó sống qua nhiều lượt gọi; gieo mỗi lượt là mỗi lượt
   thêm một bản sao của cùng một người, và lưới hiện ra hai NGUYỄN VĂN MINH. */
$da_co = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'nhan_vien' ) );
if ( 0 === $da_co ) {
	foreach ( array( 'POSH_HCM' => 'Máy tự động', 'TUTU_BT' => 'Khu vui chơi' ) as $cs => $m ) {
		$wpdb->insert( VHCC_DB::t( 'bo_phan_coso' ), array( 'coso' => $cs, 'bo_phan' => $m ) );
	}
	/* CỬA HÀNG TRƯỞNG: vai này thấy NHIỀU ô nhất — có cả tab Cửa hàng và nhóm "Quản lý cửa
	   hàng" trong lưới Ứng dụng. Chụp bằng nhân viên thường là chụp thiếu đúng mấy ô ấy. */
	$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array(
		'ma_nv' => 'MNCH2MTD0001', 'ho_ten' => 'NGUYỄN VĂN MINH', 'cua_hang' => 'POSH_HCM',
		'coso_phu' => 'TUTU_BT', 'vai_tro' => 'Cửa hàng trưởng', 'pin_dang_nhap' => '112233',
		'trang_thai_lam_viec' => 'Đang làm', 'luong_co_ban' => 8000000 ) );
	$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array(
		'ma_nv' => 'MNNV2MTD0002', 'ho_ten' => 'TRẦN THỊ LAN', 'cua_hang' => 'POSH_HCM',
		'vai_tro' => 'Nhân viên', 'pin_dang_nhap' => '445566',
		'trang_thai_lam_viec' => 'Đang làm' ) );

	/* Một lượt chấm VÀO của hôm nay, chưa có giờ ra — để tab Chấm công không trống trơn, và
	   để thấy đúng cảnh mà lời nhắc "chưa chấm ra" nói về. */
	$hom_nay = current_time( 'Y-m-d' );
	$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array(
		'coso' => 'POSH_HCM', 'ngay' => $hom_nay, 'ma_nv' => 'MNCH2MTD0001', 'hau_to' => '',
		'ho_ten' => 'NGUYỄN VĂN MINH', 'gio_vao_giay' => VHCC_DB::giay( '08:02:00' ),
		'chuan' => '08:02', 'nguon' => 'online' ) );
	foreach ( array( 1, 2, 3, 4, 5 ) as $lui ) {
		$n = gmdate( 'Y-m-d', strtotime( $hom_nay . ' -' . $lui . ' day' ) );
		$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array(
			'coso' => 'POSH_HCM', 'ngay' => $n, 'ma_nv' => 'MNCH2MTD0001', 'hau_to' => '',
			'ho_ten' => 'NGUYỄN VĂN MINH', 'gio_vao_giay' => VHCC_DB::giay( '08:00:00' ),
			'gio_ra_giay' => VHCC_DB::giay( '17:05:00' ), 'chuan' => '08:00 17:05',
			'nguon' => 'online' ) );
	}
}

$viec = isset( $_GET['viec'] ) ? (string) $_GET['viec'] : '';
if ( '' !== $viec ) {
	VHCC_Tram::cong( $viec );
	exit;
}
VHCC_Tram::render();
