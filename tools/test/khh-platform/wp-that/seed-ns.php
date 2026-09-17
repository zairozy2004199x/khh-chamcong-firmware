<?php
require_once '/tmp/wpsite/wp-load.php';
global $wpdb;
$nguoi = array(
	array( 'MNNV2KVC0008', 'Nguyễn Thị Minh Thư' ), array( 'MNNV2KVC0003', 'Nguyễn Thị Ánh Tuyết' ),
	array( 'MNNV2KVC0185', 'NGUYỄN THỊ HIỀN THỤC' ), array( 'MNNV2KVC0148', 'Võ Nguyễn Thu Giang' ),
	array( 'MNNV2MTD0004', 'Nguyễn Thị Thu' ), array( 'MNNV2KVC0140', 'Đoàn Vũ Thanh Phong' ),
	array( 'MNNV2KVC0151', 'Nguyễn Văn Hoàng' ), array( 'MNVH2KVC0001', 'Phan Thị Quế Anh' ),
	array( 'MNNV2KVC0118', 'Trương Tuấn Hào' ), array( 'MNNV2KVC0167', 'Châu Vương Mỹ Duyên' ),
);
$wpdb->query( "DELETE FROM {$wpdb->prefix}khh_docs WHERE coll IN ('staff','attendance')" );
foreach ( $nguoi as $i => $p ) {
	khh_put_doc( 'staff', 's' . ( $i + 1 ), array(
		'name' => $p[1], 'code' => $p[0],
		'office' => $i < 3 ? 'Chi Nhánh HCM' : '',
		'status' => 'active', 'role' => 0 === $i ? 'owner' : 'staff',
	) );
}
/* gán tài khoản admin vào hồ sơ đầu để đăng nhập thấy đúng quyền */
update_user_meta( 1, 'khh_role', 'owner' );
$u = get_user_by( 'id', 1 );
wp_update_user( array( 'ID' => 1, 'display_name' => 'Nguyễn Thị Minh Thư' ) );
echo 'hồ sơ nhân sự: ' . count( khh_get_coll( 'staff' ) ) . "\n";
echo 'bản ghi chấm công trong nền tảng: ' . count( khh_get_coll( 'attendance' ) ) . "\n";
