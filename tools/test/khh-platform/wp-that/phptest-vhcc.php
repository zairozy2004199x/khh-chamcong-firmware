<?php
/* Kiểm thử THẬT: WordPress + MariaDB thật, plugin Chấm Công (K&H) thật đã bật, bảng vhcc thật */
require_once '/tmp/wpsite/wp-load.php';

$fail = 0;
function t( $name, $got, $want ) {
	global $fail;
	$ok = $got === $want;
	if ( ! $ok ) { $fail++; printf( "FAIL  %-58s got=%s want=%s\n", $name, var_export( $got, true ), var_export( $want, true ) ); }
	else { printf( "PASS  %-58s got=%s\n", $name, var_export( $got, true ) ); }
}

delete_option( 'khh_cc_nguon' );
delete_transient( 'khh_cc_doc' );
delete_transient( 'khh_vhcc_co' );
delete_transient( 'khh_vhcc_coso' );

t( 'nhận ra plugin Chấm Công (K&H) qua bảng của nó', khh_vhcc_co(), true );
t( 'không khai gì vẫn biết nguồn là vhcc', khh_cc_nguon_dang_dung(), 'vhcc' );

/* --- luật công y hệt bên ấy --- */
t( 'giây → HH:MM', khh_vhcc_hhmm( 8 * 3600 + 10 * 60 + 23 ), '08:10' );
t( 'ra − vào ra phút', khh_vhcc_phut( 8 * 3600, 18 * 3600 ), 600 );
t( 'ra sớm hơn vào thì KHÔNG tính (dấu hiệu sai)', khh_vhcc_phut( 9 * 3600, 8 * 3600 ), null );
t( 'thiếu giờ ra thì không tính', khh_vhcc_phut( 8 * 3600, null ), null );

/* --- đọc thẳng bảng thật --- */
list( $docs, $st ) = khh_vhcc_doc_song( 3 );
t( 'đọc đủ dòng trong bảng vhcc', $st['rows'], 132 );
t( '  khớp 10 người có hồ sơ', $st['people'], 10 );
t( '  1 dòng của mã chưa có hồ sơ (người đã nghỉ)', $st['noStaff'], 1 );
t( '  nêu đúng mã đó', $st['lost'], array( 'MNNV9999' ) );

$byId = array();
foreach ( $docs as $d ) { $byId[ $d['id'] ] = $d; }
$mt = $byId['s1_202609'];
t( 'mã cũ NV001 (chạy song song) vẫn về đúng người', isset( $mt['days']['01'] ), true );
t( '  ngày 1 lấy giờ vào từ giây', $mt['days']['01']['in'], '08:08' );
t( '  số phút công = ra − vào, không trừ nghỉ trưa', $mt['days']['01']['m'], 593 );
t( '  cơ sở của máy ghi vào cột thiết bị', $mt['days']['01']['device'], 'KHU VUI CHƠI FUNFEST' );
t( '  nguồn "may" → Máy chấm công', $mt['days']['01']['method'], 'Máy chấm công' );

$tp = $byId['s6_202609'];   // Đoàn Vũ Thanh Phong — có hàng -CD ca đêm thứ năm
$thu5 = null;
foreach ( $tp['days'] as $k => $v ) { if ( isset( $v['ca'] ) && 'CD' === $v['ca'] ) { $thu5 = $k; break; } }
t( 'hàng -CD cùng ngày gộp vào MỘT ngày công', null !== $thu5, true );
t( '  phút của hàng chính + hàng CD', $tp['days'][ $thu5 ]['m'] > 600, true );
t( '  giờ ra lấy mốc muộn nhất (22:00 của ca đêm)', $tp['days'][ $thu5 ]['out'], '22:00' );

$th = $byId['s9_202609'];   // Trương Tuấn Hào — ngày 16 ra < vào
t( 'ra < vào: vẫn hiện giờ nhưng KHÔNG có công', isset( $th['days']['16']['m'] ), false );
t( '  giờ vào vẫn còn để người soát nhìn thấy', $th['days']['16']['in'], '09:00' );

$md = $byId['s10_202609'];  // Châu Vương Mỹ Duyên — ngày 16 chỉ có giờ vào
t( 'chỉ có giờ vào: không có giờ ra, không có công', isset( $md['days']['16']['out'] ) || isset( $md['days']['16']['m'] ), false );

/* --- qua khh_cc_song: có mốc, có đệm, sửa tay thắng --- */
$song = khh_cc_song();
t( 'khh_cc_song tự dùng nguồn vhcc', $song['stat']['nguon'], 'vhcc' );
t( '  trả đủ bản ghi', count( $song['docs'] ), 10 );
t( '  có mốc đổi', $song['ver'] > 0, true );

/* --- cơ sở tự điền từ sổ nhân viên bên ấy --- */
$staff = array();
foreach ( khh_get_coll( 'staff' ) as $id => $s ) { $s['id'] = $id; $staff[] = $s; }
$sau = khh_vhcc_dien_coso( $staff );
$cs  = array();
foreach ( $sau as $s ) { $cs[ $s['code'] ] = $s['office']; }
t( 'người chưa gán cơ sở được điền theo cửa hàng bên vhcc', $cs['MNNV2KVC0140'], 'FZ ADV AL' );
t( '  người đã gán tay thì giữ nguyên (Chi Nhánh HCM)', $cs['MNNV2KVC0008'], 'Chi Nhánh HCM' );
t( '  không ghi gì vào hồ sơ thật', khh_get_coll( 'staff' )['s6']['office'], '' );

echo $fail ? "\n$fail phép thử KHÔNG đạt\n" : "\nTất cả phép thử đều đạt\n";
exit( $fail ? 1 : 0 );
