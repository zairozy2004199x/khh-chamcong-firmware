<?php
/**
 * KIỂM CHẤM CÔNG BÙ — cửa ghi giờ THỨ BA.
 *
 * `VHCC_Cham` mở đầu bằng câu: *"Sửa giờ chấm công chỉ có đúng hai đường… Mở thêm đường thứ ba
 * để 'sửa cho nhanh' là mở đường sửa lương bằng tay mà không có dấu vết."* Anh Thắng cần đường
 * thứ ba đó (Cửa hàng trưởng bù cho nhân viên quên bấm), nên bộ thử này canh đúng cái GIÁ mà
 * câu trên đòi — có dấu vết, không đè, không tự bù cho mình.
 *
 * Chạy: php tools/test/kiem-cham-bu.php
 */

$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
require $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-db.php';
require $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-vai.php';
require $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-nhan.php';

/** Hồ sơ giả: ai có mã bắt đầu bằng NV thì coi như có hồ sơ. */
class VHCC_NhanSu {
	public static $co_quyen = true;
	public static function chuan_coso( $s ) { return trim( preg_replace( '/^CS_/', '', (string) $s ) ); }
	public static function co_quyen_coso( $u, $c ) { return self::$co_quyen; }
	public static function ho_so( $ma ) {
		return ( 0 === strpos( (string) $ma, 'NV' ) ) ? array( 'ho_ten' => 'Người ' . $ma ) : null;
	}
}
class VHCC_Luong {
	public static function tien_to_thang( $t ) {
		return preg_match( '/^(\d{4}-\d{2})/', (string) $t, $m ) ? $m[1] : '';
	}
}
require $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-bu.php';

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

vhcc_dung_bang();
global $wpdb;

$hom_nay = date( 'Y-m-d' );
$hom_qua = date( 'Y-m-d', strtotime( '-1 day' ) );
$mai     = date( 'Y-m-d', strtotime( '+1 day' ) );

$CHT   = array( 'name' => 'Anh CHT',  'role' => 'Cửa hàng trưởng', 'coso' => 'TUTU_BT', 'ma_nv' => 'NVCHT' );
$NV    = array( 'name' => 'Em NV',    'role' => 'Nhân viên',       'coso' => 'TUTU_BT', 'ma_nv' => 'NVEM' );
$ADMIN = array( 'name' => 'Admin',    'role' => 'Admin',           'coso' => '',        'ma_nv' => 'NVAD' );

/** Gọi bù cho gọn. */
function bu( $u, $dat_them = array() ) {
	return VHCC_Bu::ghi( $u, array_merge( array(
		'coso' => 'TUTU_BT', 'ngay' => date( 'Y-m-d', strtotime( '-1 day' ) ),
		'ma_nv' => 'NV001', 'vao' => '08:00', 'ra' => '17:00',
		'ly_do' => 'máy hỏng, có camera' ), $dat_them ) );
}
function hang( $ma = 'NV001', $ngay = null, $hau = '' ) {
	global $wpdb;
	$ngay = $ngay ? $ngay : date( 'Y-m-d', strtotime( '-1 day' ) );
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . VHCC_DB::t( 'cham_cong' )
		. ' WHERE coso=%s AND ngay=%s AND ma_nv=%s AND hau_to=%s', 'TUTU_BT', $ngay, $ma, $hau ), ARRAY_A );
}

// ============================================================ 1. GÁC CỬA
echo "— gác cửa —\n";
$r = bu( $NV );
t( 'Nhân viên KHÔNG bù được',
	empty( $r['ok'] ) && false !== strpos( $r['error'], 'Cửa hàng trưởng' ), $r );
teq( 'và KHÔNG có hàng nào được tạo', null, hang() );

/* 🔴 Chốt nặng nhất của lớp này: bù công là đổi thẳng ra tiền, nên không ai tự ký duyệt tiền
   của mình được — kể cả Admin. */
$r = bu( $CHT, array( 'ma_nv' => 'NVCHT' ) );
t( 'Cửa hàng trưởng KHÔNG tự bù cho mình',
	empty( $r['ok'] ) && false !== strpos( $r['error'], 'tự bù' ), $r );
$r = bu( $ADMIN, array( 'ma_nv' => 'NVAD' ) );
t( 'ADMIN cũng KHÔNG tự bù cho mình', empty( $r['ok'] ), $r );
/* Hậu tố không được dùng để lách: NVCHT-CD vẫn là NVCHT. */
$r = bu( $CHT, array( 'ma_nv' => 'NVCHT-CD' ) );
t( 'thêm hậu tố -CD cũng không lách được chốt tự bù', empty( $r['ok'] ), $r );

$r = bu( $CHT, array( 'ma_nv' => 'XX999' ) );
t( 'mã KHÔNG có hồ sơ -> chối (bù cho người không có là tạo công ma)',
	empty( $r['ok'] ) && false !== strpos( $r['error'], 'hồ sơ' ), $r );

VHCC_NhanSu::$co_quyen = false;
$r = bu( $CHT );
t( 'cơ sở ngoài phạm vi -> chối', empty( $r['ok'] ), $r );
VHCC_NhanSu::$co_quyen = true;

// ============================================================ 2. NGÀY
echo "— ngày —\n";
teq( 'hôm nay bù được', '', VHCC_Bu::ngay_hop_le( $hom_nay ) );
t( 'ngày MAI thì không', '' !== VHCC_Bu::ngay_hop_le( $mai ) );
t( 'và nói rõ vì sao', false !== strpos( VHCC_Bu::ngay_hop_le( $mai ), 'chưa tới' ) );
t( 'ngày quá xa (lương đã chốt) thì không',
	'' !== VHCC_Bu::ngay_hop_le( date( 'Y-m-d', strtotime( '-200 day' ) ) ) );
t( 'và chỉ sang Kế toán chứ không chỉ bỏ đi',
	false !== strpos( VHCC_Bu::ngay_hop_le( date( 'Y-m-d', strtotime( '-200 day' ) ) ), 'Kế toán' ) );
t( 'ngày méo -> chối', '' !== VHCC_Bu::ngay_hop_le( '12/08/2026' ) );
$r = bu( $CHT, array( 'ngay' => $mai ) );
t( 'bù cho ngày mai bị chối ở cửa ghi', empty( $r['ok'] ), $r );

// ============================================================ 3. LÝ DO
echo "— lý do —\n";
foreach ( array( '', '   ', 'ok', 'quên' ) as $ly ) {
	$r = bu( $CHT, array( 'ly_do' => $ly ) );
	t( 'lý do "' . $ly . '" quá ngắn -> chối', empty( $r['ok'] ), $r );
}
teq( 'và chưa ghi hàng nào', null, hang() );

// ============================================================ 4. GIỜ
echo "— giờ —\n";
teq( 'giây: 08:30 -> 30600', 30600, VHCC_Bu::giay( '08:30' ) );
teq( 'giây: 08:30:15 -> 30615', 30615, VHCC_Bu::giay( '08:30:15' ) );
teq( 'ô trống -> null (KHÔNG phải 0: 0 là 00:00:00)', null, VHCC_Bu::giay( '' ) );
teq( 'nửa đêm 00:00 -> 0, vẫn là giờ thật', 0, VHCC_Bu::giay( '00:00' ) );
teq( '25:00 -> null', null, VHCC_Bu::giay( '25:00' ) );
$r = bu( $CHT, array( 'vao' => '', 'ra' => '' ) );
t( 'không nhập giờ nào -> chối', empty( $r['ok'] ), $r );
$r = bu( $CHT, array( 'vao' => '17:00', 'ra' => '08:00' ) );
t( 'giờ ra sớm hơn giờ vào -> chối, và chỉ sang hàng ca đêm',
	empty( $r['ok'] ) && false !== strpos( $r['error'], '-CD' ), $r );

// ============================================================ 5. BÙ ĐƯỢC
echo "— bù được —\n";
$r = bu( $CHT );
t( 'Cửa hàng trưởng bù được cho nhân viên', ! empty( $r['ok'] ), $r );
teq( 'kể đúng hai ô đã ghi', array( 'vao' => '08:00', 'ra' => '17:00' ), $r['daGhi'] );
$h = hang();
t( 'hàng đã vào bảng chấm công', is_array( $h ), $h );
teq( 'giờ vào đúng', VHCC_DB::giay( '08:00:00' ), (int) $h['gio_vao_giay'] );
teq( 'giờ ra đúng',  VHCC_DB::giay( '17:00:00' ), (int) $h['gio_ra_giay'] );
teq( 'mang nhãn nguồn "bu" — phân biệt được với giờ máy', 'bu', $h['nguon'] );
teq( 'tên lấy từ HỒ SƠ, không phải người bù tự gõ', 'Người NV001', $h['ho_ten'] );
t( 'ghi chú giữ lại lý do', false !== strpos( (string) $h['ghi_chu'], 'máy hỏng' ), $h['ghi_chu'] );

/* Nhật ký: MỖI Ô GIỜ một dòng. */
$nk = VHCC_Bu::ds_nhat_ky( $ADMIN, 'TUTU_BT', substr( $hom_qua, 0, 7 ) );
teq( 'nhật ký ghi ĐÚNG HAI dòng (mỗi ô giờ một dòng)', 2, count( $nk ) );
$o = array();
foreach ( $nk as $x ) { $o[ $x['o_gio'] ] = $x; }
teq( 'có dòng cho giờ vào', true, isset( $o['vao'] ) );
teq( 'có dòng cho giờ ra',  true, isset( $o['ra'] ) );
teq( 'nhật ký nhớ AI bù', 'Anh CHT', $o['vao']['nguoi_bu'] );
teq( 'nhớ mã người bù',   'NVCHT',   $o['vao']['ma_nguoi_bu'] );
teq( 'nhớ VAI của người bù', 'CUA_HANG_TRUONG', $o['vao']['vai_nguoi_bu'] );
t( 'nhớ lý do', false !== strpos( (string) $o['vao']['ly_do'], 'máy hỏng' ) );
t( 'nhớ lúc bù', '' !== trim( (string) $o['vao']['tao_luc'] ) );

// ============================================================ 6. KHÔNG ĐÈ
echo "— không đè lên giờ đã có —\n";
/* Máy đã ghi một ngày đủ cặp -> bù KHÔNG được đụng vào. */
VHCC_Nhan::ghi_gio( 'TUTU_BT', $hom_nay, 'NV002', 'Người NV002', VHCC_DB::giay( '07:55:00' ), '', 'may' );
VHCC_Nhan::ghi_gio( 'TUTU_BT', $hom_nay, 'NV002', 'Người NV002', VHCC_DB::giay( '16:40:00' ), '', 'may' );
$r = bu( $CHT, array( 'ngay' => $hom_nay, 'ma_nv' => 'NV002', 'vao' => '06:00', 'ra' => '23:00' ) );
t( 'ngày đã đủ giờ -> chối hẳn, và chỉ sang gắn cờ',
	empty( $r['ok'] ) && false !== strpos( $r['error'], 'gắn cờ' ), $r );
$h2 = hang( 'NV002', $hom_nay );
teq( 'giờ vào của MÁY còn nguyên', VHCC_DB::giay( '07:55:00' ), (int) $h2['gio_vao_giay'] );
teq( 'giờ ra của MÁY còn nguyên',  VHCC_DB::giay( '16:40:00' ), (int) $h2['gio_ra_giay'] );
teq( 'nguồn vẫn là "may", không bị bù nhuộm sang',  'may', $h2['nguon'] );

/* Thiếu MỖI giờ ra -> bù điền được đúng ô trống, ô đã có thì bỏ qua VÀ NÓI RA. */
VHCC_Nhan::ghi_gio( 'TUTU_BT', $hom_nay, 'NV003', 'Người NV003', VHCC_DB::giay( '08:10:00' ), '', 'may' );
$r = bu( $CHT, array( 'ngay' => $hom_nay, 'ma_nv' => 'NV003', 'vao' => '06:00', 'ra' => '17:30' ) );
t( 'bù vào ngày thiếu giờ ra thì chạy', ! empty( $r['ok'] ), $r );
teq( 'chỉ ghi giờ RA', array( 'ra' => '17:30' ), $r['daGhi'] );
t( 'và NÓI RA là đã bỏ qua giờ vào', ! empty( $r['boQua'] )
	&& false !== strpos( $r['boQua'][0], 'giờ vào' ), $r );
$h3 = hang( 'NV003', $hom_nay );
teq( 'giờ vào của máy KHÔNG bị 06:00 đè lên', VHCC_DB::giay( '08:10:00' ), (int) $h3['gio_vao_giay'] );
teq( 'giờ ra được điền', VHCC_DB::giay( '17:30:00' ), (int) $h3['gio_ra_giay'] );
teq( 'nguồn nâng lên "hon-hop" — một ngày hai đường ghi', 'hon-hop', $h3['nguon'] );
teq( 'nhật ký chỉ thêm ĐÚNG MỘT dòng cho lượt này', 1,
	count( array_filter( VHCC_Bu::ds_nhat_ky( $ADMIN, 'TUTU_BT', substr( $hom_nay, 0, 7 ) ),
		function ( $x ) { return 'NV003' === $x['ma_nv']; } ) ) );

/* Bù vào hàng ca đêm -CD là hàng RIÊNG, không đụng hàng chính. */
$r = bu( $CHT, array( 'ngay' => $hom_nay, 'ma_nv' => 'NV003-CD', 'vao' => '22:00', 'ra' => '23:30' ) );
t( 'bù được vào hàng ca đêm', ! empty( $r['ok'] ), $r );
$hcd = hang( 'NV003', $hom_nay, 'CD' );
t( 'hàng -CD là hàng riêng', is_array( $hcd ), $hcd );
teq( 'và hàng chính không bị đụng', VHCC_DB::giay( '08:10:00' ), (int) hang( 'NV003', $hom_nay )['gio_vao_giay'] );

// ============================================================ 7. NHẬT KÝ KHÔNG XOÁ ĐƯỢC
echo "— nhật ký —\n";
$than = file_get_contents( $goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-bu.php' );
t( 'lớp bù KHÔNG có câu DELETE nào', false === stripos( $than, 'DELETE' ), 'có DELETE' );
t( 'và không có câu UPDATE nào lên nhật ký',
	false === strpos( $than, "update( VHCC_DB::t( 'cham_bu'" ) );
/* Bù đi qua đúng cổng ghi chung, không tự viết INSERT vào bảng chấm công — nếu tự viết thì luật
   "chỉ nới, không thu hẹp" có bản thứ hai, và hai bản sớm muộn lệch nhau. */
t( 'bù KHÔNG tự viết INSERT/UPDATE vào bảng chấm công',
	false === strpos( $than, "insert( VHCC_DB::t( 'cham_cong'" )
	&& false === strpos( $than, "update( VHCC_DB::t( 'cham_cong'" ) );
t( 'mà đi qua đúng VHCC_Nhan::ghi_gio', false !== strpos( $than, 'VHCC_Nhan::ghi_gio' ) );

if ( count( $truot ) ) {
	echo "HỎNG: " . count( $truot ) . "\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "\n✓ SẠCH — $dat phép: bù CHỈ điền ô trống, có nhật ký, không ai tự bù cho mình.\n";
