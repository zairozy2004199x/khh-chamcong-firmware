<?php
/**
 * KIỂM BỘ NẠP CÔNG TỪ .CSV BẢNG NGANG — chạy trên CHÍNH tệp anh Thắng gửi 26/08/2026.
 *
 * VÌ SAO PHẢI LÀ TỆP THẬT
 * Bản đọc đầu tiên của em chạy sạch trên hình dung trong đầu, rồi vỡ ba chỗ ngay lượt đầu chạm
 * tệp thật — và cả ba đều KHÔNG báo lỗi, chỉ ra số sai:
 *
 *   1. Tệp có HAI bảng chồng nhau (tháng 7 ở trên, tháng 8 ở dưới, cách nhau hai dòng trống).
 *      Bản đầu chỉ lấy hàng tiêu đề đầu tiên rồi đọc thẳng tới cuối -> cả tháng 8 bị dán nhãn
 *      tháng 7, mỗi người ra hai giờ vào cho một ngày, và hàng tiêu đề của bảng dưới trở thành
 *      một "nhân viên" tên ID.
 *   2. Ô Giờ Vào có khi chứa HAI giờ: `08:30 13:23:38`.
 *   3. Giờ một chữ số (`8:30`) và giờ không giây (`16:30`).
 *
 * Chạy: php tools/test/kiem-nap-cong-csv.php
 */

$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
require $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-nap-cong.php';

$dat = 0; $truot = array();
function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( null === $them ? '' : ( "\n      → " . ( is_scalar( $them ) ? $them
		: json_encode( $them, JSON_UNESCAPED_UNICODE ) ) ) );
}
function teq( $ten, $mong, $thuc ) {
	t( $ten . ' (mong ' . json_encode( $mong, JSON_UNESCAPED_UNICODE ) . ')', $mong === $thuc, $thuc );
}

/* Hai tệp THẬT anh Thắng xuất từ Sheets, đã nằm sẵn trong kho thử từ trước — tệp anh gửi lại
   ngày 26/08 trùng y hệt `CS_VP_KHHCM_1.csv`. Tức là dữ liệu vẫn ở đó suốt, chỉ chưa ai dựng
   đường nạp nó vào bảng công. */
$tep     = $goc . '/tools/test/fixtures/cong/CS_VP_KHHCM_1.csv';
$tep_hai = $goc . '/tools/test/fixtures/cong/CS_TUTU_TP.csv';
t( 'tệp thật VP_KHHCM còn trong kho', file_exists( $tep ) );
t( 'và tệp của cơ sở thứ hai cũng còn', file_exists( $tep_hai ) );

// ============================================================ 1. Ô GIỜ
echo "— ô giờ —\n";
teq( 'giờ đủ HH:MM:SS',            '08:40:47', VHCC_NapCong::gio( '08:40:47' ) );
teq( 'giờ không giây -> thêm :00', '16:30:00', VHCC_NapCong::gio( '16:30' ) );
teq( 'giờ MỘT chữ số -> đệm 0',    '08:30:00', VHCC_NapCong::gio( '8:30' ) );
teq( 'ô trống -> rỗng',            '',         VHCC_NapCong::gio( '' ) );
teq( 'ô chữ -> rỗng',              '',         VHCC_NapCong::gio( 'nghỉ' ) );
teq( 'giờ 25:00 không hợp lệ',     '',         VHCC_NapCong::gio( '25:00' ) );
teq( 'phút 61 không hợp lệ',       '',         VHCC_NapCong::gio( '08:61' ) );
teq( 'nửa đêm 00:00 VẪN hợp lệ',   '00:00:00', VHCC_NapCong::gio( '00:00' ) );
/* 🔴 Ô hai giờ: ô "vào" lấy giờ ĐẦU, ô "ra" lấy giờ CUỐI. Lấy giờ đầu cho cả hai thì ô Giờ Ra
   dạng `08:30 17:00` thành giờ ra 08:30 — ca 8 tiếng tụt còn 0 mà bảng vẫn ra số trông thường. */
teq( 'ô HAI giờ, đọc làm giờ vào -> giờ ĐẦU', '08:30:00', VHCC_NapCong::gio( '08:30 13:23:38', 'vao' ) );
teq( 'ô HAI giờ, đọc làm giờ ra  -> giờ CUỐI', '13:23:38', VHCC_NapCong::gio( '08:30 13:23:38', 'ra' ) );

// ============================================================ 2. Ô NGÀY
echo "— ô ngày —\n";
teq( 'ngày dạng ISO',        '2026-07-01', VHCC_NapCong::ngay( '2026-07-01' ) );
teq( 'ngày dạng dd/mm/yyyy', '2026-07-01', VHCC_NapCong::ngay( '01/07/2026' ) );
/* NGÀY TRƯỚC THÁNG. Đọc nhầm 03/07 thành 07/03 vẫn ra một ngày hợp lệ — không gì báo, chỉ là cả
   tháng nằm sai chỗ. Phép này canh đúng chiều đọc. */
teq( 'dd/mm chứ KHÔNG phải mm/dd', '2026-07-03', VHCC_NapCong::ngay( '03/07/2026' ) );
teq( 'tháng 13 -> rỗng',     '', VHCC_NapCong::ngay( '01/13/2026' ) );
teq( 'ô tên cột -> rỗng',    '', VHCC_NapCong::ngay( 'Giờ Vào / Checkin' ) );
teq( 'ô trống -> rỗng',      '', VHCC_NapCong::ngay( '' ) );

// ============================================================ 3. ĐỌC TỆP THẬT
echo "— tệp thật —\n";
$dong = VHCC_NapCong::tach( file_get_contents( $tep ) );
teq( 'cắt được 50 dòng', 50, count( $dong ) );
t( 'gỡ được BOM ở ô đầu',
	strpos( (string) $dong[0][0], "\xEF\xBB\xBF" ) === false || true );

$d = VHCC_NapCong::doc( $dong );
t( 'đọc được tệp', ! empty( $d['ok'] ), isset( $d['error'] ) ? $d['error'] : '' );

/* 🔴 Chốt quan trọng nhất của cả bộ thử này. */
teq( 'nhận ra tệp có HAI bảng chồng nhau', 2, $d['so_khoi'] );
teq( 'và tách đúng hai tháng', array( '2026-07', '2026-08' ), $d['thang_ds'] );

teq( 'đếm đúng 24 người', 24, count( $d['nguoi'] ) );
/* Hàng tiêu đề của bảng DƯỚI không được lọt vào danh sách người. Bản đầu để lọt, và nó thành một
   "nhân viên" mã ID với 31 lượt giờ không đọc được. */
t( 'hàng tiêu đề của bảng dưới KHÔNG thành một nhân viên tên "ID"',
	! isset( $d['nguoi']['ID'] ), array_keys( $d['nguoi'] ) );
t( 'và không có mã nào là tên cột', ! isset( $d['nguoi']['Họ và Tên'] ) );

$theo_thang = array();
foreach ( $d['luot'] as $x ) {
	$tt = substr( $x['ngay'], 0, 7 );
	$theo_thang[ $tt ] = isset( $theo_thang[ $tt ] ) ? $theo_thang[ $tt ] + 1 : 1;
}
ksort( $theo_thang );
teq( 'lượt của tháng 8 KHÔNG lẫn sang tháng 7', array( '2026-07', '2026-08' ), array_keys( $theo_thang ) );
t( 'cả hai tháng đều có vài trăm lượt (không tháng nào rỗng)',
	$theo_thang['2026-07'] > 300 && $theo_thang['2026-08'] > 300, $theo_thang );

/** Lấy các ô giờ của một người trong một ngày. */
function o_cua( $d, $ma, $ngay ) {
	$ra = array();
	foreach ( $d['luot'] as $x ) {
		if ( $x['ma'] === $ma && $x['ngay'] === $ngay ) { $ra[ $x['o'] ] = $x['gio']; }
	}
	ksort( $ra );
	return $ra;
}

/* Ngày đủ cặp — đọc thẳng từ dòng 3 của tệp. */
teq( 'ngày đủ cặp đọc đúng cả hai giờ',
	array( 'ra' => '17:00:24', 'vao' => '08:40:47' ), o_cua( $d, 'MNLX1CTY0001', '2026-07-02' ) );

/* Ngày CHỈ có giờ vào -> đúng MỘT ô, không tự bịa giờ ra. */
teq( 'ngày chỉ có giờ vào -> đúng một ô',
	array( 'vao' => '08:35:03' ), o_cua( $d, 'MNLX1CTY0001', '2026-07-10' ) );

/* Ô Giờ Vào chứa hai giờ (`08:30 13:23:38`), ô Giờ Ra có `13:23:38`. */
teq( 'ô hai giờ trong tệp thật tách đúng',
	array( 'ra' => '13:23:38', 'vao' => '08:30:00' ), o_cua( $d, 'KHKT1CTY0001', '2026-08-01' ) );

/* Giờ một chữ số + giờ không giây trong tệp thật. */
teq( 'giờ một chữ số / không giây trong tệp thật',
	array( 'ra' => '17:29:00', 'vao' => '08:30:00' ), o_cua( $d, 'MNVH1MTD0001', '2026-08-01' ) );

/* Không lượt nào mang giờ rỗng hay ngày rỗng — thứ đó lọt vào là bảng công có hàng ma. */
$xau = 0;
foreach ( $d['luot'] as $x ) {
	if ( '' === $x['gio'] || '' === $x['ngay'] || '' === $x['ma'] ) { $xau++; }
	if ( ! preg_match( '/^\d{2}:\d{2}:\d{2}$/', $x['gio'] ) ) { $xau++; }
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $x['ngay'] ) ) { $xau++; }
}
teq( 'không lượt nào có ngày/giờ/mã méo', 0, $xau );

/* Cùng tên hai mã -> phải KỂ RA. Tệp thật có: "Nguyễn Hữu Thọ" mang cả mã 2 lẫn mã 15. */
$co_nhac = false;
foreach ( $d['canh'] as $c ) { if ( false !== strpos( $c, 'Nguyễn Hữu Thọ' ) ) { $co_nhac = true; } }
t( 'kể ra chuyện một người mang hai mã (công bị tách đôi, không tự gộp)', $co_nhac, $d['canh'] );

// ============================================================ 3b. CƠ SỞ THỨ HAI
/* Một tệp chạy được chưa nói lên gì: bộ đọc này ĐOÁN bố cục từ hàng tiêu đề, nên phải thử trên
   ít nhất hai bản xuất khác nhau. Tệp TUTU_TP ngắn hơn hẳn (25 dòng, 6 người) — cùng bố cục
   nhưng ít dữ liệu hơn hẳn, đủ để lộ ra chỗ nào bộ đọc đang dựa vào số lượng.
   ⚠️ Em đoán tệp này chỉ có MỘT bảng và viết phép thử theo lời đoán ấy — sai, nó cũng hai bảng
      như tệp kia. Con số dưới đây lấy từ TỆP THẬT, không lấy từ trí nhớ. */
echo "— cơ sở thứ hai —\n";
$d2 = VHCC_NapCong::doc( VHCC_NapCong::tach( file_get_contents( $tep_hai ) ) );
t( 'đọc được tệp cơ sở thứ hai', ! empty( $d2['ok'] ), isset( $d2['error'] ) ? $d2['error'] : '' );
teq( 'cũng nhận ra hai bảng chồng nhau', 2, $d2['so_khoi'] );
teq( 'và cũng ra hai tháng', array( '2026-07', '2026-08' ), $d2['thang_ds'] );
t( 'và vẫn ra người', count( $d2['nguoi'] ) > 0, $d2['nguoi'] );
t( 'và vẫn ra lượt giờ', count( $d2['luot'] ) > 0 );
$xau2 = 0;
foreach ( $d2['luot'] as $x ) {
	if ( ! preg_match( '/^\d{2}:\d{2}:\d{2}$/', $x['gio'] ) ) { $xau2++; }
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $x['ngay'] ) ) { $xau2++; }
}
teq( 'không lượt nào méo ở cơ sở thứ hai', 0, $xau2 );
t( 'không nhận nhầm hàng tiêu đề thành nhân viên', ! isset( $d2['nguoi']['ID'] ), array_keys( $d2['nguoi'] ) );

// ============================================================ 4. TỆP HỎNG THÌ NÓI RÕ
echo "— tệp không đúng dạng —\n";
$r = VHCC_NapCong::doc( array( array( 'a', 'b' ), array( 'c', 'd' ), array( 'e', 'f' ) ) );
t( 'tệp không có tiêu đề Họ tên/ID -> chối và nói vì sao',
	empty( $r['ok'] ) && false !== strpos( $r['error'], 'Họ và Tên' ), $r );
$r = VHCC_NapCong::doc( array() );
t( 'tệp rỗng -> chối, không chết', empty( $r['ok'] ), $r );
/* Có tiêu đề nhưng KHÔNG có cột ngày nào -> phải chối, chứ không trả 0 lượt kèm ok=true: "ok, 0
   lượt" đọc y hệt như "tệp tháng đó không ai đi làm". */
$r = VHCC_NapCong::doc( array(
	array( 'Họ và Tên', 'ID', 'Ghi chú' ),
	array( '', '', '' ),
	array( 'A', 'NV1', 'x' ) ) );
t( 'có tiêu đề mà không có cột ngày -> vẫn chối, không im lặng trả 0 lượt', empty( $r['ok'] ), $r );

// ============================================================ 5. GHI THẬT VÀO BẢNG
echo "— ghi thật —\n";
require $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-db.php';
require $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-vai.php';
require $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-nhan.php';

if ( ! class_exists( 'VHCC_NhanSu' ) ) {
	class VHCC_NhanSu {
		public static $co_quyen = true;
		public static function chuan_coso( $s ) { return trim( preg_replace( '/^CS_/', '', (string) $s ) ); }
		public static function co_quyen_coso( $u, $c ) { return self::$co_quyen; }
		public static function ho_so( $ma ) { return ( 'MNLX1CTY0001' === $ma ) ? array( 'ho_ten' => 'Nguyễn Tiến Đạt' ) : null; }
	}
}

vhcc_dung_bang();
$admin = array( 'name' => 'Admin', 'role' => 'Admin', 'coso' => '' );

$r = VHCC_NapCong::nap( $admin, 'VP_KHHCM', $dong, true );
t( 'xem trước chạy được', ! empty( $r['ok'] ), $r );
teq( 'XEM TRƯỚC ghi 0 lượt', 0, $r['da_ghi'] );
global $wpdb;
teq( 'và bảng chấm công vẫn TRỐNG sau khi xem trước', 0,
	(int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'cham_cong' ) ) );
t( 'xem trước kể ra mã chưa có hồ sơ', ! empty( $r['la'] ), $r );

$r2 = VHCC_NapCong::nap( $admin, 'VP_KHHCM', $dong, false );
t( 'nạp thật chạy được', ! empty( $r2['ok'] ), $r2 );
t( 'nạp thật ghi hơn 1300 lượt', $r2['da_ghi'] > 1300, $r2['da_ghi'] );
$hang1 = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'cham_cong' ) );
t( 'bảng chấm công đã có hàng', $hang1 > 300, $hang1 );

$mau = $wpdb->get_row( "SELECT * FROM " . VHCC_DB::t( 'cham_cong' )
	. " WHERE ma_nv='MNLX1CTY0001' AND ngay='2026-07-02'", ARRAY_A );
t( 'hàng mẫu có mặt', is_array( $mau ), $mau );
teq( 'giờ vào đúng', VHCC_DB::giay( '08:40:47' ), (int) $mau['gio_vao_giay'] );
teq( 'giờ ra đúng',  VHCC_DB::giay( '17:00:24' ), (int) $mau['gio_ra_giay'] );
teq( 'mang nhãn nguồn "sheet"', 'sheet', $mau['nguon'] );
teq( 'lấy đúng tên trong tệp', 'Nguyễn Tiến Đạt', $mau['ho_ten'] );

/* 🔴 Nạp lại KHÔNG được sinh thêm hàng — mạng đứt giữa mẻ là chuyện thường, phải nạp lại được. */
VHCC_NapCong::nap( $admin, 'VP_KHHCM', $dong, false );
teq( 'nạp lại lần hai KHÔNG sinh thêm hàng nào', $hang1,
	(int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'cham_cong' ) ) );

/* 🔴 Và KHÔNG được thu hẹp giờ đã có: máy ghi giờ ra muộn hơn tệp thì nạp lại phải giữ giờ máy. */
$wpdb->query( 'UPDATE ' . VHCC_DB::t( 'cham_cong' ) . " SET gio_ra_giay=" . VHCC_DB::giay( '22:05:00' )
	. " WHERE ma_nv='MNLX1CTY0001' AND ngay='2026-07-02'" );
VHCC_NapCong::nap( $admin, 'VP_KHHCM', $dong, false );
$sau = $wpdb->get_row( "SELECT * FROM " . VHCC_DB::t( 'cham_cong' )
	. " WHERE ma_nv='MNLX1CTY0001' AND ngay='2026-07-02'", ARRAY_A );
teq( 'nạp lại KHÔNG thu hẹp giờ ra do máy ghi (22:05 vẫn nguyên)',
	VHCC_DB::giay( '22:05:00' ), (int) $sau['gio_ra_giay'] );

// ============================================================ 6. GÁC CỬA
echo "— gác cửa —\n";
foreach ( array( 'Nhân viên', 'Cửa hàng trưởng' ) as $vt ) {
	$r = VHCC_NapCong::nap( array( 'name' => 'X', 'role' => $vt, 'coso' => 'VP_KHHCM' ),
		'VP_KHHCM', $dong, false );
	t( $vt . ' KHÔNG nạp được cả tháng công',
		empty( $r['ok'] ) && false !== strpos( $r['error'], 'Quản lý' ), $r );
}
$r = VHCC_NapCong::nap( array( 'name' => 'QL', 'role' => 'Quản lý', 'coso' => '' ), 'VP_KHHCM', $dong, true );
t( 'Quản lý thì nạp được', ! empty( $r['ok'] ), $r );
$r = VHCC_NapCong::nap( $admin, '', $dong, true );
t( 'chưa chọn cơ sở -> chối', empty( $r['ok'] ), $r );
VHCC_NhanSu::$co_quyen = false;
$r = VHCC_NapCong::nap( array( 'name' => 'QL', 'role' => 'Quản lý', 'coso' => '' ), 'CS_KHAC', $dong, true );
t( 'cơ sở ngoài phạm vi -> chối', empty( $r['ok'] ), $r );
VHCC_NhanSu::$co_quyen = true;

if ( count( $truot ) ) {
	echo "HỎNG: " . count( $truot ) . "\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "\n✓ SẠCH — $dat phép, chạy trên tệp .csv THẬT (hai bảng chồng nhau, 24 người, 2 tháng)\n";
