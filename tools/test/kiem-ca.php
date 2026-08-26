<?php
/**
 * KIỂM TÁCH CA — "bạn đó làm ca nào, ca đó mấy tiếng, từ ca nào đến ca nào".
 *
 * 🔴 Chỗ dễ sai nhất KHÔNG phải phép cộng giờ, mà là CA QUA NỬA ĐÊM. Ca 3 chạy 22:00 → 06:00;
 *    so kiểu `tu <= x && x < den` thì ca này không bao giờ khớp (22:00 không nhỏ hơn 06:00), và
 *    cả ca đêm của mọi cơ sở lặng lẽ biến mất khỏi bảng — tổng giờ chỉ hụt đi, không có gì báo.
 *
 * Chạy: php tools/test/kiem-ca.php
 */

$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
require $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-db.php';
require $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-vai.php';

class VHCC_NhanSu {
	public static $co_quyen = true;
	public static function chuan_coso( $s ) { return trim( preg_replace( '/^CS_/', '', (string) $s ) ); }
	public static function co_quyen_coso( $u, $c ) { return self::$co_quyen; }
}
class VHCC_Cham {
	public static function chu_gio( $phut ) {
		if ( null === $phut || '' === $phut ) { return '—'; }
		$p = (int) $phut; $h = intdiv( $p, 60 ); $m = $p % 60;
		return $h . 'h' . ( $m ? ' ' . $m . 'm' : '' );
	}
}
class VHCC_Luong {
	public static $kho = array();
	public static function cai_dat( $khoa, $mac_dinh = null ) {
		return isset( self::$kho[ $khoa ] ) ? self::$kho[ $khoa ] : $mac_dinh;
	}
	public static function dat_cai_dat( $khoa, $gia_tri, $u ) { self::$kho[ $khoa ] = $gia_tri; return array( 'ok' => true ); }
}
require $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-ca.php';

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
/** Giờ 'HH:mm' -> giây, cho gọn. */
function g( $s ) { list( $h, $p ) = explode( ':', $s ); return ( (int) $h * 3600 ) + ( (int) $p * 60 ); }

// ============================================================ 1. ĐỌC GIỜ
echo "— đọc giờ —\n";
teq( "'6:00' -> '06:00'",  '06:00', VHCC_Ca::gio( '6:00' ) );
teq( "'22:00' giữ nguyên", '22:00', VHCC_Ca::gio( '22:00' ) );
teq( 'rỗng -> rỗng',        '',     VHCC_Ca::gio( '' ) );
teq( "'25:00' -> rỗng",     '',     VHCC_Ca::gio( '25:00' ) );
teq( "'abc' -> rỗng",       '',     VHCC_Ca::gio( 'abc' ) );
teq( "phút: '06:30' -> 390", 390,   VHCC_Ca::phut( '06:30' ) );
teq( 'phút của giờ sai -> null', null, VHCC_Ca::phut( 'xx' ) );

// ============================================================ 2. GIAO NHAU
echo "— giao nhau —\n";
teq( 'trùng khít',            480, VHCC_Ca::giao( 0, 480, 0, 480 ) );
teq( 'nằm gọn bên trong',     120, VHCC_Ca::giao( 100, 220, 0, 480 ) );
teq( 'chồm nửa đầu',           60, VHCC_Ca::giao( 420, 600, 0, 480 ) );
teq( 'rời hẳn -> 0',            0, VHCC_Ca::giao( 600, 700, 0, 480 ) );
/* Chạm mép KHÔNG phải là giao: ca 06–14 và ca 14–22 dính nhau ở 14:00. Tính mép là mọi lượt
   được cộng thêm 0 phút vào ca kế — vô hại — nhưng nó làm ca kế XUẤT HIỆN trong danh sách, và
   bảng sẽ nói người ta "làm 2 ca" trong khi chỉ làm 1. */
teq( 'chạm mép không tính là giao', 0, VHCC_Ca::giao( 480, 600, 0, 480 ) );

// ============================================================ 3. DANH SÁCH CA
echo "— danh sách ca —\n";
$U = array( 'name' => 'CHT', 'role' => 'Cửa hàng trưởng', 'coso' => 'TUTU_BD' );
teq( 'chưa khai gì -> dùng ca mặc định', 'mac_dinh', VHCC_Ca::nguon_ca( 'TUTU_BD' ) );
teq( 'và mặc định có đúng 3 ca', 3, count( VHCC_Ca::cua( 'TUTU_BD' ) ) );

$r = VHCC_Ca::luu( $U, 'TUTU_BD', array(
	array( 'ten' => 'Ca sáng', 'tu' => '06:00', 'den' => '14:00' ),
	array( 'ten' => 'Ca chiều', 'tu' => '14:00', 'den' => '22:00' ),
	array( 'ten' => 'Ca đêm',  'tu' => '22:00', 'den' => '06:00' ),
	array( 'ten' => '',        'tu' => '08:00', 'den' => '12:00' ),   // thiếu tên -> bỏ
	array( 'ten' => 'Hỏng',    'tu' => '',      'den' => '12:00' ),   // thiếu giờ -> bỏ
	array( 'ten' => 'Rỗng',    'tu' => '09:00', 'den' => '09:00' ),   // dài 0 -> bỏ
) );
t( 'lưu được ca', ! empty( $r['ok'] ), $r );
teq( 'ba ca hỏng bị bỏ, còn 3', 3, $r['so_ca'] );
teq( 'cơ sở này giờ dùng ca RIÊNG', 'rieng', VHCC_Ca::nguon_ca( 'TUTU_BD' ) );
teq( 'cơ sở khác vẫn dùng mặc định', 'mac_dinh', VHCC_Ca::nguon_ca( 'TUTU_GV' ) );
/* Lưu danh sách RỖNG = bỏ khai riêng, quay về ca chung — KHÔNG phải "cơ sở không có ca nào".
   Hiểu nhầm chỗ này là mọi giờ công của cơ sở rơi ra ngoài mọi ca. */
VHCC_Ca::luu( $U, 'TUTU_BD', array() );
teq( 'lưu danh sách rỗng = bỏ khai riêng', 'mac_dinh', VHCC_Ca::nguon_ca( 'TUTU_BD' ) );
t( 'và vẫn còn ca để dùng', count( VHCC_Ca::cua( 'TUTU_BD' ) ) > 0 );

/* Gác cửa. */
$r = VHCC_Ca::luu( array( 'name' => 'NV', 'role' => 'Nhân viên', 'coso' => 'TUTU_BD' ), 'TUTU_BD',
	VHCC_Ca::MAC_DINH );
t( 'Nhân viên KHÔNG khai được ca', empty( $r['ok'] ), $r );
VHCC_NhanSu::$co_quyen = false;
$r = VHCC_Ca::luu( $U, 'CS_LA', VHCC_Ca::MAC_DINH );
t( 'cơ sở ngoài phạm vi -> chối', empty( $r['ok'] ), $r );
VHCC_NhanSu::$co_quyen = true;

// ============================================================ 4. TÁCH CA
echo "— tách ca —\n";
$CA = VHCC_Ca::MAC_DINH;    // Ca 1 06–14 · Ca 2 14–22 · Ca 3 22–06

/** Lấy [tên ca => số phút] cho gọn. */
function ph( $tach ) {
	$o = array();
	foreach ( $tach['ds'] as $x ) { $o[ $x['ten'] ] = $x['phut']; }
	return $o;
}

$x = VHCC_Ca::tach( $CA, g( '06:00' ), g( '14:00' ) );
teq( 'trùng khít Ca 1', array( 'Ca 1' => 480 ), ph( $x ) );
teq( 'và không có phút nào ngoài ca', 0, $x['ngoai_ca'] );
teq( 'từ ca nào đến ca nào: một ca thì nói một tên', 'Ca 1', VHCC_Ca::tu_den( $x ) );

$x = VHCC_Ca::tach( $CA, g( '10:00' ), g( '18:00' ) );
teq( 'vắt qua hai ca thì tách đôi', array( 'Ca 1' => 240, 'Ca 2' => 240 ), ph( $x ) );
teq( 'và nói rõ từ ca nào đến ca nào', 'Ca 1 → Ca 2', VHCC_Ca::tu_den( $x ) );
teq( 'tổng phút vẫn đúng', 480, $x['tong_phut'] );

/* 🔴 CA QUA NỬA ĐÊM. */
$x = VHCC_Ca::tach( $CA, g( '22:00' ), g( '23:59' ) );
teq( 'vào 22:00 ra 23:59 -> thuộc Ca 3 (ca qua nửa đêm)', array( 'Ca 3' => 119 ), ph( $x ) );
/* Hàng ca đêm đã trải phẳng: ra 06:00 hôm sau = 30 tiếng tính từ 00:00 hôm trước. */
$x = VHCC_Ca::tach( $CA, g( '22:00' ), g( '06:00' ) + 86400 );
teq( 'vào 22:00 ra 06:00 HÔM SAU -> trọn Ca 3, 8 tiếng', array( 'Ca 3' => 480 ), ph( $x ) );
teq( 'không phút nào rơi ra ngoài ca', 0, $x['ngoai_ca'] );
/* Lượt bắt đầu SAU nửa đêm thuộc ca đêm của HÔM QUA — phải xét khung ca lùi một ngày. */
$x = VHCC_Ca::tach( $CA, g( '02:00' ), g( '05:00' ) );
teq( 'vào 02:00 ra 05:00 -> vẫn là Ca 3 (của đêm hôm trước)', array( 'Ca 3' => 180 ), ph( $x ) );
/* Vắt từ ca đêm sang ca sáng. */
$x = VHCC_Ca::tach( $CA, g( '04:00' ), g( '09:00' ) );
teq( 'vắt từ Ca 3 sang Ca 1', array( 'Ca 1' => 180, 'Ca 3' => 120 ), ph( $x ) );

/* Ca cuối tuần khai riêng thì T7/CN phải dùng giờ ấy. */
$CA_W = array( array( 'ten' => 'Ca 1', 'tu' => '06:00', 'den' => '14:00',
	'tuW' => '08:00', 'denW' => '12:00' ) );
teq( 'ngày thường dùng giờ thường', array( 'Ca 1' => 480 ),
	ph( VHCC_Ca::tach( $CA_W, g( '06:00' ), g( '14:00' ), false ) ) );
teq( 'cuối tuần dùng giờ cuối tuần', array( 'Ca 1' => 240 ),
	ph( VHCC_Ca::tach( $CA_W, g( '06:00' ), g( '14:00' ), true ) ) );

/* Giờ không thuộc ca nào phải được KỂ RA, không nuốt. */
$CA_HEP = array( array( 'ten' => 'Ca 1', 'tu' => '08:00', 'den' => '12:00', 'tuW' => '', 'denW' => '' ) );
$x = VHCC_Ca::tach( $CA_HEP, g( '06:00' ), g( '14:00' ) );
teq( 'chỉ 4 tiếng nằm trong ca', array( 'Ca 1' => 240 ), ph( $x ) );
teq( 'bốn tiếng còn lại được kể là NGOÀI CA, không bị nuốt', 240, $x['ngoai_ca'] );
teq( 'và tổng hai phần bằng đúng tổng giờ làm', $x['tong_phut'], 240 + $x['ngoai_ca'] );

/* Lượt vài phút không được kể thành một ca. */
$x = VHCC_Ca::tach( $CA, g( '13:55' ), g( '14:05' ) );
teq( 'lượt 10 phút không đẻ ra ca nào', array(), ph( $x ) );
teq( 'nhưng vẫn kể là ngoài ca', 10, $x['ngoai_ca'] );

/* Thiếu giờ / giờ ngược -> không tách được, và KHÔNG được đoán. */
teq( 'thiếu giờ ra -> không ca nào', array(), ph( VHCC_Ca::tach( $CA, g( '08:00' ), null ) ) );
teq( 'giờ ra sớm hơn giờ vào -> không ca nào', array(),
	ph( VHCC_Ca::tach( $CA, g( '17:00' ), g( '08:00' ) ) ) );
teq( 'không ca nào thì câu "từ–đến" để trống', '', VHCC_Ca::tu_den( VHCC_Ca::tach( $CA, null, null ) ) );

/* Dòng chữ cho chú thích. */
$chu = VHCC_Ca::chu( VHCC_Ca::tach( $CA, g( '10:00' ), g( '18:00' ) ) );
t( 'chú thích nói tên ca, số giờ và khung giờ',
	false !== strpos( $chu, 'Ca 1 4h (06:00–14:00)' )
	&& false !== strpos( $chu, 'Ca 2 4h (14:00–22:00)' ), $chu );
t( 'và nói cả phần ngoài ca',
	false !== strpos( VHCC_Ca::chu( VHCC_Ca::tach( $CA_HEP, g( '06:00' ), g( '14:00' ) ) ), 'ngoài ca 4h' ) );

// ============================================================ 5. TỔNG KHÔNG ĐƯỢC HỤT
echo "— tổng không hụt —\n";
/* Chốt bao trùm: với MỌI cặp giờ, tổng phút theo ca cộng phần ngoài ca phải bằng đúng tổng giờ
   làm. Hụt một phút nghĩa là có giờ rơi vào khe giữa hai ca mà không ai nhận. */
$lech = array();
for ( $v = 0; $v < 24; $v++ ) {
	foreach ( array( 1, 4, 8, 12 ) as $dai ) {
		$x = VHCC_Ca::tach( $CA, $v * 3600, ( $v + $dai ) * 3600 );
		$cong = 0;
		foreach ( $x['ds'] as $o ) { $cong += $o['phut']; }
		if ( $cong + $x['ngoai_ca'] !== $x['tong_phut'] ) {
			$lech[] = sprintf( '%02d:00 +%dh -> %d+%d != %d', $v, $dai, $cong, $x['ngoai_ca'], $x['tong_phut'] );
		}
	}
}
teq( '96 cặp giờ: tổng theo ca + ngoài ca luôn bằng tổng giờ làm', array(), $lech );
/* Và không ca nào được tính hai lần (giao của ba vị trí khung ca phải rời nhau). */
$qua = array();
for ( $v = 0; $v < 24; $v++ ) {
	$x = VHCC_Ca::tach( $CA, $v * 3600, ( $v + 8 ) * 3600 );
	foreach ( $x['ds'] as $o ) {
		if ( $o['phut'] > 480 ) { $qua[] = sprintf( '%02d:00 %s = %d phút', $v, $o['ten'], $o['phut'] ); }
	}
}
teq( 'không ca nào nhận quá 8 tiếng từ một lượt 8 tiếng', array(), $qua );

if ( count( $truot ) ) {
	echo "HỎNG: " . count( $truot ) . "\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "\n✓ SẠCH — $dat phép: tách ca chạy đúng cả với ca qua nửa đêm.\n";
