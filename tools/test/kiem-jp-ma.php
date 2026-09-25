<?php
/**
 * SINH MÃ BẢN GHI CỦA JP — `RP20260921-0007`
 * =============================================================================================
 *
 * 🔴 ĐIỀU DUY NHẤT THẬT SỰ QUAN TRỌNG: HAI DÒNG KHÔNG BAO GIỜ ĐƯỢC MANG CHUNG MỘT MÃ.
 *    Mã là khoá chính, và nó nằm trong mọi khoá ngoại. Hai báo cáo chung mã là một cái đè lên
 *    cái kia, hoặc mọi dòng chi tiết của cả hai trộn vào nhau — và không ai dựng lại được.
 *
 * Bản gốc bịt khe hở ấy bằng `LockService` chờ 20 giây. Bản này ghi ngay rồi để KHOÁ CHÍNH của
 * bảng đích nói KHÔNG nếu trùng, rồi lùi sang số kế tiếp. Bài này phải chứng minh cái chốt ấy
 * thật sự cắn, chứ không chỉ có trong chú thích.
 *
 * Chạy: php tools/test/kiem-jp-ma.php
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
foreach ( array( 'db', 'doc', 'nguon', 'ma' ) as $f ) { require_once $plg . 'class-vhjp-' . $f . '.php'; }

global $wpdb;
vhcp_stub_dung_bang( VHJP_DB::bang(), $wpdb->prefix . 'vhjp_' );
t( 'dựng được lược đồ để thử', count( vhcp_stub_loi_ddl() ) === 0, vhcp_stub_loi_ddl() );

// ============================================================ 1. Khuôn mã
teq( 'khuôn mã đúng bản gốc', 'RP20260921-0007', VHJP_Ma::tao( 'RP', 7, '2026-09-21' ) );
teq( 'số thứ tự luôn 4 chữ số',  'RP20260921-0001', VHJP_Ma::tao( 'RP', 1, '2026-09-21' ) );
teq( 'và không cắt khi vượt 4 chữ số', 'RP20260921-12345', VHJP_Ma::tao( 'RP', 12345, '2026-09-21' ) );
teq( 'tiền tố hai chữ cũng được', 'PM20260921-0003', VHJP_Ma::tao( 'PM', 3, '2026-09-21' ) );

/* 🔴 Ngày lấy theo giờ Việt Nam, không theo giờ máy chủ. Hosting đặt sai múi là mọi mã sinh
   sau 17h mang ngày hôm sau, và bảng đối soát theo ngày lệch hẳn một hàng. */
$vn = new DateTime( 'now', new DateTimeZone( 'Asia/Ho_Chi_Minh' ) );
teq( 'hôm nay tính theo giờ Việt Nam', $vn->format( 'Y-m-d' ), VHJP_Ma::hom_nay() );

/* 🔴 PHÉP TRÊN MỘT MÌNH LÀ CHƯA ĐỦ: Việt Nam và UTC cùng ngày suốt 17 trong 24 giờ, nên nó
   xanh phần lớn thời gian DÙ mã lấy sai múi. Lượt đục thử "đổi sang UTC" sống sót vì đúng
   chuyện đó. Phải ghim một khoảnh khắc mà hai múi giờ KHÁC NGÀY nhau. */
$moc = new DateTime( '2026-09-21 18:00:00', new DateTimeZone( 'UTC' ) );   // = 01:00 ngày 22 ở VN
teq( '🔴 18h UTC ngày 21 là đã sang ngày 22 ở Việt Nam', '2026-09-22', VHJP_Ma::hom_nay( $moc ) );
$moc2 = new DateTime( '2026-09-21 10:00:00', new DateTimeZone( 'UTC' ) );  // = 17:00 cùng ngày
teq( 'còn 10h UTC thì vẫn là ngày 21', '2026-09-21', VHJP_Ma::hom_nay( $moc2 ) );
/* Và canh chính phép đo: giờ máy chủ đặt lệch đi đâu cũng không được ảnh hưởng. */
$tz_cu = date_default_timezone_get();
date_default_timezone_set( 'Pacific/Kiritimati' );
$van_dung = ( '2026-09-22' === VHJP_Ma::hom_nay( $moc ) );
date_default_timezone_set( $tz_cu );
t( '🔴 và KHÔNG chạy theo múi giờ máy chủ', $van_dung );

// ============================================================ 2. Số chạy tiếp, không nhảy
$a = VHJP_Ma::them( 'JP_Reports', 'RP', array( 'locationName' => 'Aeon' ) );
t( 'ghi được bản ghi đầu', false !== $a, $a );
$hn = str_replace( '-', '', VHJP_Ma::hom_nay() );
teq( 'mã đầu tiên trong ngày là 0001', 'RP' . $hn . '-0001', $a['id'] );

$b = VHJP_Ma::them( 'JP_Reports', 'RP', array( 'locationName' => 'Phú Quốc' ) );
teq( 'mã kế tiếp là 0002', 'RP' . $hn . '-0002', $b['id'] );
teq( 'sổ có đúng 2 dòng', 2, count( VHJP_Nguon::doc( 'JP_Reports' ) ) );

/* Tiền tố khác thì đếm riêng — `RP` và `PM` không ăn số của nhau. */
$p = VHJP_Ma::them( 'JP_Payments', 'PM', array( 'amount' => 250000 ) );
teq( 'tiền tố khác đếm riêng từ 0001', 'PM' . $hn . '-0001', $p['id'] );

// ============================================================ 3. 🔴 Nối tiếp dữ liệu ĐÃ CHUYỂN
/* Dữ liệu cũ bê từ Google Sheets sang mang sẵn mã. Số mới phải nối TIẾP nó, không thì lượt ghi
   đầu tiên sau khi chuyển đâm thẳng vào một dòng đã có. */
VHJP_Nguon::them( 'JP_Zones', array( 'id' => 'ZN' . $hn . '-0042', 'name' => 'khu cũ' ) );
teq( '🔴 nối tiếp đúng số lớn nhất đang có', 43, VHJP_Ma::so_ke_tiep( 'JP_Zones', 'ZN' ) );
$z = VHJP_Ma::them( 'JP_Zones', 'ZN', array( 'name' => 'khu mới' ) );
teq( 'và mã mới là 0043', 'ZN' . $hn . '-0043', $z['id'] );
teq( 'KHÔNG đè lên dòng cũ', 'khu cũ', VHJP_Nguon::tim_mot( 'JP_Zones', 'id', 'ZN' . $hn . '-0042' )['name'] );

/* Mã của NGÀY KHÁC không được kéo số của hôm nay theo. */
VHJP_Nguon::them( 'JP_Zones', array( 'id' => 'ZN20200101-9999', 'name' => 'năm xưa' ) );
teq( 'mã ngày khác KHÔNG ảnh hưởng số hôm nay', 44, VHJP_Ma::so_ke_tiep( 'JP_Zones', 'ZN' ) );

// ============================================================ 4. 🔴 Đâm vào mã đã có thì LÙI
/* ══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 CHẶN ĐÚNG MỘT MÃ — KHÔNG PHẢI "THÊM SẴN MỘT DÒNG RỒI GỌI LẠI".
 *
 * Cách hiển nhiên (thêm sẵn dòng mang mã `$so` rồi gọi `them()`) KHÔNG đi qua nhánh lùi số:
 * `so_ke_tiep()` chạy lại, thấy dòng vừa thêm, và tự trả về `$so + 1` — lượt ghi trúng ngay từ
 * vòng đầu. Lượt đục thử "bỏ hẳn phép lùi số" vì thế SỐNG SÓT, và bài kiểm xanh cho một bản
 * không hề biết lùi.
 *
 * Phải dựng đúng cảnh THẬT: hai lượt chạy song song, lượt kia chiếm mất mã ở đúng khoảng giữa
 * `so_ke_tiep()` và câu ghi. Cò chặn dưới đây chối riêng một mã, mô phỏng đúng khoảnh khắc ấy.
 * ══════════════════════════════════════════════════════════════════════════════════════════ */
$so = VHJP_Ma::so_ke_tiep( 'JP_Reports', 'RP' );
$ma_bi_chiem = VHJP_Ma::tao( 'RP', $so );
$wpdb->exec_raw( 'CREATE TRIGGER vhjp_chiem BEFORE INSERT ON ' . VHJP_Nguon::bang( 'JP_Reports' )
	. " WHEN NEW.id = '" . $ma_bi_chiem . "'"
	. " BEGIN SELECT RAISE(ABORT, 'UNIQUE constraint failed: id'); END" );
$c = VHJP_Ma::them( 'JP_Reports', 'RP', array( 'locationName' => 'của mình' ) );
$wpdb->exec_raw( 'DROP TRIGGER vhjp_chiem' );

t( '🔴 mã bị chiếm -> vẫn ghi được, không trả về false', false !== $c, $c );
teq( '🔴 và LÙI sang số kế tiếp', VHJP_Ma::tao( 'RP', $so + 1 ), is_array( $c ) ? $c['id'] : $c );
teq( 'không dòng nào mang mã bị chiếm', null, VHJP_Nguon::tim_mot( 'JP_Reports', 'id', $ma_bi_chiem ) );

/* Bị chiếm liên tiếp nhiều số thì vẫn phải tìm ra chỗ trống. */
$so2 = VHJP_Ma::so_ke_tiep( 'JP_Reports', 'RP' );
for ( $i = 0; $i < 5; $i++ ) {
	VHJP_Nguon::them( 'JP_Reports', array( 'id' => VHJP_Ma::tao( 'RP', $so2 + $i ), 'locationName' => 'chiếm' ) );
}
$d = VHJP_Ma::them( 'JP_Reports', 'RP', array( 'locationName' => 'chen được' ) );
t( 'chiếm liền 5 số thì vẫn chen được', false !== $d, $d );
teq( 'và đúng số trống đầu tiên', VHJP_Ma::tao( 'RP', $so2 + 5 ), $d['id'] );

/* 🔴 Và mã KHÔNG BAO GIỜ trùng: đếm mã khác nhau phải bằng đếm dòng. */
$ds = VHJP_Nguon::doc( 'JP_Reports' );
$ma = array_column( $ds, 'id' );
teq( '🔴 không hai dòng nào chung mã', count( $ds ), count( array_unique( $ma ) ) );

// ============================================================ 5. Hỏng thật thì đừng quay vòng
/* "Đâm vào mã đã có" và "cơ sở dữ liệu hỏng" cùng trả false từ lượt ghi. Thử lại cho ca đầu là
   đúng; thử lại cho ca sau là quay 20 vòng vô ích rồi vẫn hỏng. */
t( 'nhận ra câu lỗi trùng mã của MySQL',
	VHJP_Ma::la_trung( "Duplicate entry 'RP1-0001' for key 'PRIMARY'" ) );
t( 'và của SQLite (bệ đỡ thử)',
	VHJP_Ma::la_trung( 'UNIQUE constraint failed: wp_vhjp_bao_cao.id' ) );
t( '🔴 nhưng KHÔNG nhận nhầm lỗi khác thành lỗi trùng',
	! VHJP_Ma::la_trung( 'disk I/O error' ) && ! VHJP_Ma::la_trung( 'no such table' ) );

$wpdb->exec_raw( 'CREATE TRIGGER vhjp_chan BEFORE INSERT ON ' . VHJP_Nguon::bang( 'JP_Zones' )
	. " BEGIN SELECT RAISE(ABORT, 'o cung hong'); END" );
$truoc = $wpdb->q_count;
$hong = VHJP_Ma::them( 'JP_Zones', 'ZN', array( 'name' => 'hỏng' ) );
$so_luot = $wpdb->q_count - $truoc;
$wpdb->exec_raw( 'DROP TRIGGER vhjp_chan' );

teq( '🔴 cơ sở dữ liệu hỏng -> trả false, không gật đầu', false, $hong );
t( '🔴 và DỪNG NGAY, không quay 20 vòng vô ích', $so_luot <= 4, $so_luot . ' lượt xuống CSDL' );

// ============================================================ 6. Tab lạ
teq( 'tab lạ -> false, không nổ', false, VHJP_Ma::them( 'JP_KhongCo', 'XX', array( 'a' => 1 ) ) );
teq( 'và số kế tiếp của tab lạ là 0', 0, VHJP_Ma::so_ke_tiep( 'JP_KhongCo', 'XX' ) );

// ============================================================ kết
if ( $truot ) {
	echo "HỎNG: " . count( $truot ) . "\n";
	foreach ( $truot as $x ) { echo "  ✗ $x\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — mã chạy tiếp, không đụng dòng đã có, hỏng thật thì dừng ngay.\n";
