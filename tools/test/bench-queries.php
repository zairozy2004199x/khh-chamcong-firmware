<?php
/**
 * Đo số lệnh xuống database của các màn hình nặng, với 2 mức dữ liệu.
 * Nếu số lệnh TĂNG theo số dự án/đợt thì đang đọc lặp (N+1).
 *   php tools/test/bench-queries.php
 */
require_once __DIR__ . '/wp-stub.php';
// Tham số 1 (tùy chọn): đường dẫn plugin khác — để so số đo giữa 2 phiên bản.
$plugin_dir = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : ( dirname( dirname( __DIR__ ) ) . '/wordpress/vhcp-chi-phi' );
vhcp_test_boot( $plugin_dir );
global $wpdb;

$today = ( new DateTime( 'now', VHCP_Util::tz() ) )->format( 'd/m/Y' );

function seed( $n, $today ) {
	for ( $i = 0; $i < $n; $i++ ) {
		$da = VHCP_DuAn::create_du_an( 'Tháo dỡ', 'Dự án ' . uniqid(), 'KT' );
		VHCP_DuAn::add_line( $da['maDA'], array( 'noiDung' => 'Hạng mục lớn', 'duToan' => 1000000, 'hinhThuc' => 'Tạm ứng' ) );
		VHCP_DuAn::add_line( $da['maDA'], array( 'noiDung' => 'Con', 'thucTe' => 900000, 'capCha' => 'Hạng mục lớn' ) );
		VHCP_DuAn::submit( $da['maDA'] );
		VHCP_DuAn::approve( $da['maDA'], 'KT' );

		$bp = VHCP_BP::create( 'Công tác', 'Đợt ' . uniqid(), 'A', 'VR SORA', '08/2026', 'Admin' );
		VHCP_BP::add_line( $bp['ma'], array( 'noiDung' => 'Vé', 'duToan' => 100000, 'thucTe' => 120000, 'ngay' => $today ) );

		$mk = VHCP_MK::create_don( 'VR SORA', 'CD ' . uniqid(), '08/2026', 'FB', 'MKT' );
		VHCP_MK::add_line( $mk['ma'], array( 'noiDung' => 'Ads', 'duToan' => 500000, 'thucTe' => 480000, 'ngay' => $today ) );

		$d = VHCP_Don::create_don( 'T8/2026', 'NV' );
		VHCP_Don::add_line( $d['maDon'], array( 'coso' => 'VR SORA', 'ngay' => $today, 'phanLoaiTT' => 'Thanh toán cá nhân', 'nhom' => 'Chi phí cơ sở', 'noiDung' => 'X', 'thanhTien' => 200000 ) );
		VHCP_Don::gui_duyet_tam_ung( $d['maDon'] );
		VHCP_Don::duyet_tam_ung( $d['maDon'], 'QL', '' );
		VHCP_Don::cap_tam_ung( $d['maDon'], 'KT', 'Tiền mặt' );
		VHCP_Don::gui_quyet_toan( $d['maDon'] );
	}
}

function measure( $label ) {
	global $wpdb;
	$out = array();
	$run = function ( $name, $fn, $prep = null ) use ( &$out, $wpdb ) {
		$arg = $prep ? $prep() : null;           // dựng dữ liệu trước, KHÔNG tính vào số đo
		delete_transient( 'vhcp_cfgstatic' );    // đo trường hợp xấu nhất: cache nguội
		$wpdb->q_count = 0;
		$fn( $arg );
		$out[ $name ] = $wpdb->q_count;
	};
	$run( 'getBootstrap',      function () { VHCP_Don::get_bootstrap(); } );
	$run( 'listDuAn',          function () { VHCP_DuAn::list_du_an(); } );
	$run( 'listBP',            function () { VHCP_BP::list_bp( 'all' ); } );
	$run( 'listMkDon',         function () { VHCP_MK::list_don( 'all' ); } );
	$run( 'getPendingModules', function () { VHCP_Report::pending_modules(); } );
	$run( 'getVanHanhTuan',    function () { VHCP_Report::van_hanh_tuan( '' ); } );
	$run( 'getGianReport',     function () { VHCP_Report::gian_report( 'VR SORA' ); } );
	$run( 'getFinanceReport',  function () { VHCP_Report::finance( array() ); } );
	$run( 'exportMisa',        function () { VHCP_Misa::export_misa( 'all', 'chuaxuat', 'all' ); } );
	$run( 'exportMisaKyThuat', function () { VHCP_Misa::export_ky_thuat(); } );
	$run( 'exportMisaBP',      function () { VHCP_Misa::export_bp( 'all' ); } );
	$run( 'exportMisaMkt',     function () { VHCP_Misa::export_marketing(); } );
	$run(
		'getDon (1 đơn)',
		function ( $ma ) { VHCP_Don::get_don( $ma ); },
		function () { $r = VHCP_Don::list_dons(); return $r[0]['maDon']; }
	);
	// Luôn duyệt 5 đơn MỚI ở trạng thái "Chờ quyết toán" để 2 lần đo làm đúng cùng lượng việc.
	$run(
		'duyệt QT 5 đơn',
		function ( $m ) { VHCP_Don::xac_nhan_qt_cn_nhieu( $m, 'KT' ); },
		function () {
			$today = ( new DateTime( 'now', VHCP_Util::tz() ) )->format( 'd/m/Y' );
			$m = array();
			for ( $i = 0; $i < 5; $i++ ) {
				$d = VHCP_Don::create_don( 'T8/2026', 'NV' );
				VHCP_Don::add_line( $d['maDon'], array( 'coso' => 'VR SORA', 'ngay' => $today, 'phanLoaiTT' => 'Thanh toán cá nhân', 'nhom' => 'Chi phí cơ sở', 'noiDung' => 'Y', 'thanhTien' => 100000 ) );
				VHCP_Don::gui_duyet_tam_ung( $d['maDon'] );
				VHCP_Don::duyet_tam_ung( $d['maDon'], 'QL', '' );
				VHCP_Don::cap_tam_ung( $d['maDon'], 'KT', 'Tiền mặt' );
				VHCP_Don::gui_quyet_toan( $d['maDon'] );
				$m[] = $d['maDon'];
			}
			return $m;
		}
	);
	return $out;
}

seed( 3, $today );
$a = measure( '3' );
seed( 9, $today );   // gấp 4 lần dữ liệu
$b = measure( '12' );

printf( "%-22s %10s %10s %s\n", 'Màn hình', '3 bộ', '12 bộ', 'Kết luận' );
printf( "%s\n", str_repeat( '-', 62 ) );
$bad = 0;
foreach ( $a as $k => $v ) {
	$w   = $b[ $k ];
	$grow = $w - $v;
	$note = ( $grow <= 0 ) ? 'ổn (không tăng)' : ( '⚠ tăng +' . $grow . ' theo dữ liệu' );
	if ( $grow > 0 ) { $bad++; }
	printf( "%-22s %10d %10d %s\n", $k, $v, $w, $note );
}
$over = array();
foreach ( $b as $k => $v ) { if ( $v > 20 ) { $over[] = $k . ' (' . $v . ' lệnh)'; } }

echo "\n" . ( $bad ? ( $bad . ' màn hình còn đọc lặp theo số bản ghi.' ) : 'Không màn hình nào đọc lặp theo số bản ghi.' ) . "\n";
if ( $over ) { echo 'Vượt ngưỡng 20 lệnh/màn hình: ' . implode( ', ', $over ) . "\n"; }
exit( ( $bad || $over ) ? 1 : 0 );
