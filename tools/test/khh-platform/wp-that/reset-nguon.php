<?php
require_once '/tmp/wpsite/wp-load.php';
delete_option( 'khh_cc_nguon' );
delete_option( 'khh_cc_ver' );
delete_transient( 'khh_cc_doc' );
delete_transient( 'khh_vhcc_co' );
delete_transient( 'khh_vhcc_coso' );
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->prefix}khh_docs WHERE coll='attendance'" );
echo "đã xoá nguồn và dữ liệu chấm công trong nền tảng\n";
