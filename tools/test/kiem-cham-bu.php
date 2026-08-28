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

// ============================================================ 6b. SỬA ĐÈ (Admin)
/* Anh Thắng 26/08/2026: *"admin có quyền chỉnh sửa lại giờ công cho nhân viên"*.
   Đây là cửa DUY NHẤT xoá được thứ máy đã ghi, nên nó bị canh nặng nhất tệp này. */
echo "— sửa đè —\n";

function sua( $u, $dat_them = array() ) {
	return VHCC_Bu::sua( $u, array_merge( array(
		'coso' => 'TUTU_BT', 'ngay' => date( 'Y-m-d', strtotime( '-1 day' ) ),
		'ma_nv' => 'NV009', 'ly_do' => 'máy lệch đồng hồ, đối chiếu camera' ), $dat_them ) );
}

/* Dựng một ngày đã có đủ giờ, ghi qua đúng cổng máy. */
$ND = date( 'Y-m-d', strtotime( '-1 day' ) );
VHCC_Nhan::ghi_gio( 'TUTU_BT', $ND, 'NV009', 'Người NV009', VHCC_DB::giay( '08:00:00' ), '', 'may' );
VHCC_Nhan::ghi_gio( 'TUTU_BT', $ND, 'NV009', 'Người NV009', VHCC_DB::giay( '17:00:00' ), '', 'may' );
teq( 'dựng được ngày có đủ giờ, nguồn máy', 'may', hang( 'NV009' )['nguon'] );

/* ---- gác cửa ---- */
/* 🔴 CỬA HÀNG TRƯỞNG NAY SỬA ĐÈ ĐƯỢC — anh Thắng 28/08/2026: *"Cửa hàng trưởng được phép sửa
   cả giờ công đã chấm"*. Trước đó (26/08) chính anh chốt Admin; đây là anh đổi ý.
   ⚠️ ĐỔI BẬC KHÔNG ĐƯỢC KÉO THEO ĐỔI PHẠM VI hay bỏ dấu vết — ba chốt còn lại canh ngay dưới. */
$r = sua( $CHT, array( 'vao' => '09:00' ) );
t( '🔴 Cửa hàng trưởng sửa đè được giờ của cơ sở mình', ! empty( $r['ok'] ), $r );
teq( 'và giờ mới vào đúng ô', VHCC_DB::giay( '09:00:00' ), (int) hang( 'NV009' )['gio_vao_giay'] );
/* Trả lại cảnh cũ cho những phép thử phía dưới. */
sua( $ADMIN, array( 'vao' => '08:00' ) );
teq( 'trả lại giờ cũ để chạy tiếp', VHCC_DB::giay( '08:00:00' ),
	(int) hang( 'NV009' )['gio_vao_giay'] );

/* 🔴 CƠ SỞ KHÁC THÌ VẪN CHỐI — đây là chốt còn lại sau khi bậc đã hạ. */
$r_xa = VHCC_Bu::sua( $CHT, array( 'coso' => 'JP_HCM', 'ngay' => $ND, 'ma_nv' => 'NV009',
	'vao' => '09:00', 'ly_do' => 'thử sửa sang cơ sở khác' ) );
t( '🔴 nhưng KHÔNG sửa được cơ sở khác', empty( $r_xa['ok'] ), $r_xa );
/* 🔴 VẪN BẮT GHI VÌ SAO. Bậc hạ xuống thì lý do người ta gõ vào là thứ duy nhất còn tra ngược
   được — mất nó là bảng công sửa được mà không ai biết vì sao. */
$r_kolydo = VHCC_Bu::sua( $CHT, array( 'coso' => 'TUTU_BT', 'ngay' => $ND, 'ma_nv' => 'NV009',
	'vao' => '09:00', 'ly_do' => '' ) );
t( '🔴 và vẫn bắt ghi vì sao', empty( $r_kolydo['ok'] ), $r_kolydo );

/* 🔴 CHỐT `sua_gio` PHẢI LÀ MỘT ĐẦU VIỆC RIÊNG, không nhập vào `vi_sao_khong_duoc()`.
   Sau khi bậc hạ xuống Cửa hàng trưởng thì hai chốt đòi CÙNG một bậc — nên chỉ có NGOẠI LỆ
   KHOÁ RIÊNG mới tách được chúng ra. Giữ tách để Admin khoá lẻ được cho từng người, và để mai
   kia siết lại thì sửa MỘT dòng trong bảng vai. */
VHCC_Vai::dat_ngoai_le( $ADMIN, 'nv:' . $CHT['ma_nv'], 'sua_gio', 'khoa' );
$r_khoa = sua( $CHT, array( 'vao' => '09:30' ) );
t( '🔴 khoá riêng sua_gio thì cửa hàng trưởng ấy không sửa được nữa',
	empty( $r_khoa['ok'] ), $r_khoa );
teq( 'và giờ cũ còn nguyên', VHCC_DB::giay( '08:00:00' ),
	(int) hang( 'NV009' )['gio_vao_giay'] );
VHCC_Vai::dat_ngoai_le( $ADMIN, 'nv:' . $CHT['ma_nv'], 'sua_gio', '' );
/* ⚠️ Sửa sang một giờ KHÁC giờ đang có: `VHCC_Bu::sua` coi "không đổi gì" là không có việc để
   làm, nên đặt lại đúng 08:00 thì nó chối — mà chối ấy không nói gì về khoá quyền. */
$r_mo = sua( $CHT, array( 'vao' => '08:15' ) );
t( 'bỏ khoá thì sửa lại được', ! empty( $r_mo['ok'] ), $r_mo );
sua( $ADMIN, array( 'vao' => '08:00' ) );
$r = sua( $NV, array( 'vao' => '09:00' ) );
t( 'Nhân viên càng không', empty( $r['ok'] ), $r );

/* 🔴 Sửa giờ của MÌNH là viết lại tiền của mình — nặng hơn bù, vì bù chỉ thêm được vào ô trống
   còn sửa thì viết lại được cả ngày. Chốt tự-bù dùng chung, phải còn hiệu lực ở đây. */
VHCC_Nhan::ghi_gio( 'TUTU_BT', $ND, 'NVAD', 'Admin', VHCC_DB::giay( '08:00:00' ), '', 'may' );
$r = sua( $ADMIN, array( 'ma_nv' => 'NVAD', 'vao' => '06:00' ) );
t( '🔴 ADMIN KHÔNG tự sửa giờ cho mình', empty( $r['ok'] ) && false !== strpos( $r['error'], 'tự bù' ), $r );
teq( 'và giờ của chính Admin còn nguyên',
	VHCC_DB::giay( '08:00:00' ), (int) hang( 'NVAD', $ND )['gio_vao_giay'] );

/* ---- lý do bắt buộc, y như bù ---- */
$r = sua( $ADMIN, array( 'vao' => '09:00', 'ly_do' => 'sai' ) );
t( 'lý do dưới 5 ký tự -> chối', empty( $r['ok'] ) && false !== strpos( $r['error'], 'vì sao' ), $r );

/* ---- ngày ngoài cửa sổ / chưa có dòng ---- */
$r = sua( $ADMIN, array( 'ngay' => $mai, 'vao' => '09:00' ) );
t( 'ngày chưa tới -> chối', empty( $r['ok'] ), $r );
$r = sua( $ADMIN, array( 'ma_nv' => 'NV007', 'vao' => '09:00' ) );
t( 'chưa có dòng nào thì chỉ sang Chấm công bù, KHÔNG tự tạo dòng mới',
	empty( $r['ok'] ) && false !== strpos( $r['error'], 'bù' ), $r );

/* ---- 🔴 THU HẸP ĐƯỢC. Đây là điều `ghi_gio()` cố ý không làm được. ---- */
$r = sua( $ADMIN, array( 'vao' => '09:30' ) );
t( 'Admin sửa được giờ vào', ! empty( $r['ok'] ), $r );
teq( '🔴 giờ vào MUỘN HƠN giờ cũ vẫn ghi được (ghi_gio thì không)',
	VHCC_DB::giay( '09:30:00' ), (int) hang( 'NV009' )['gio_vao_giay'] );
teq( 'giờ ra không đụng tới', VHCC_DB::giay( '17:00:00' ), (int) hang( 'NV009' )['gio_ra_giay'] );
/* 🔴 Hàng đã sửa tay thì THÔI là sổ ghi máy — phép đối chiếu phải thôi đếm nó. */
teq( '🔴 nguồn đổi thành "sua", không còn là "may"', 'sua', hang( 'NV009' )['nguon'] );
teq( 'ô chuẩn tính lại theo cặp mới', '09:30 17:00', hang( 'NV009' )['chuan'] );

/* ---- 🔴 Ô TRỐNG = GIỮ NGUYÊN, KHÔNG PHẢI XOÁ ---- */
$r = sua( $ADMIN, array( 'ra' => '16:00' ) );          // không gõ giờ vào
t( 'sửa mỗi giờ ra được', ! empty( $r['ok'] ), $r );
teq( '🔴 để trống ô giờ vào thì giờ vào GIỮ NGUYÊN, không bị xoá',
	VHCC_DB::giay( '09:30:00' ), (int) hang( 'NV009' )['gio_vao_giay'] );
teq( 'và giờ ra đã đổi', VHCC_DB::giay( '16:00:00' ), (int) hang( 'NV009' )['gio_ra_giay'] );

/* ---- gõ SAI dạng phải báo lỗi, không được lặng lẽ thành xoá trắng ---- */
$r = sua( $ADMIN, array( 'vao' => '8h30' ) );
t( '🔴 gõ sai dạng giờ -> báo lỗi', empty( $r['ok'] ) && false !== strpos( $r['error'], 'dạng' ), $r );
teq( 'và KHÔNG xoá mất giờ vào', VHCC_DB::giay( '09:30:00' ), (int) hang( 'NV009' )['gio_vao_giay'] );

/* ---- xoá trắng phải là hành động RIÊNG, cố ý ---- */
$r = sua( $ADMIN, array( 'xoa_ra' => 1 ) );
t( 'tích ô xoá trắng thì xoá được', ! empty( $r['ok'] ), $r );
teq( '🔴 giờ ra thành rỗng (không phải 00:00)', null, hang( 'NV009' )['gio_ra_giay'] );
teq( 'ô chuẩn chỉ còn giờ vào', '09:30', hang( 'NV009' )['chuan'] );
/* Xoá cả hai thì HÀNG VẪN CÒN — xoá hàng là mất luôn ghi_chu và dấu vết ghi_luc. */
$r = sua( $ADMIN, array( 'xoa_vao' => 1 ) );
t( 'xoá nốt giờ vào được', ! empty( $r['ok'] ), $r );
t( '🔴 hàng vẫn còn, chỉ trống giờ', is_array( hang( 'NV009' ) ), hang( 'NV009' ) );
teq( 'và ô chuẩn rỗng', '', hang( 'NV009' )['chuan'] );

/* ---- không có gì đổi thì nói thẳng, đừng ghi một dòng nhật ký rỗng nghĩa ---- */
$r = sua( $ADMIN, array( 'xoa_vao' => 1, 'xoa_ra' => 1 ) );
t( 'sửa mà không đổi gì -> chối', empty( $r['ok'] ) && false !== strpos( $r['error'], 'thay đổi' ), $r );

/* ---- giờ ra phải muộn hơn giờ vào ---- */
$r = sua( $ADMIN, array( 'vao' => '17:00', 'ra' => '08:00' ) );
t( 'giờ ra sớm hơn giờ vào -> chối', empty( $r['ok'] ), $r );

/* ---- 🔴 NHẬT KÝ GHI CŨ -> MỚI ---- */
VHCC_Nhan::ghi_gio( 'TUTU_BT', $ND, 'NV010', 'Người NV010', VHCC_DB::giay( '07:15:00' ), '', 'may' );
sua( $ADMIN, array( 'ma_nv' => 'NV010', 'vao' => '08:45', 'ly_do' => 'máy lệch 90 phút' ) );
$nk10 = array_values( array_filter( VHCC_Bu::ds_nhat_ky( $ADMIN, 'TUTU_BT', substr( $ND, 0, 7 ) ),
	function ( $x ) { return 'NV010' === $x['ma_nv']; } ) );
teq( 'một lượt sửa một ô -> đúng MỘT dòng nhật ký', 1, count( $nk10 ) );
teq( 'dòng ấy ghi rõ là việc SỬA, không phải bù', 'sua', $nk10[0]['viec'] );
teq( '🔴 và giữ GIỜ CŨ', VHCC_DB::giay( '07:15:00' ), (int) $nk10[0]['gio_cu_giay'] );
teq( 'kèm giờ mới', VHCC_DB::giay( '08:45:00' ), (int) $nk10[0]['gio_giay'] );
teq( 'kèm người làm', 'Admin', $nk10[0]['nguoi_bu'] );
teq( 'và vai của người làm', 'ADMIN', $nk10[0]['vai_nguoi_bu'] );

/* 🔴 XOÁ TRẮNG phải ghi là null, KHÔNG phải 0. `(int) null` là 0 — tức sổ ghi "sửa thành 00:00"
   trong khi thật ra là "xoá trắng". Hai chuyện khác hẳn nhau, chỉ khác nhau một dấu ngoặc. */
sua( $ADMIN, array( 'ma_nv' => 'NV010', 'xoa_vao' => 1, 'ly_do' => 'chấm nhầm người, xoá đi' ) );
$nk10 = array_values( array_filter( VHCC_Bu::ds_nhat_ky( $ADMIN, 'TUTU_BT', substr( $ND, 0, 7 ) ),
	function ( $x ) { return 'NV010' === $x['ma_nv']; } ) );
$dong_xoa = null;
foreach ( $nk10 as $x ) { if ( null === $x['gio_giay'] ) { $dong_xoa = $x; } }
t( '🔴 lượt xoá trắng ghi giờ mới là RỖNG, không phải 00:00', null !== $dong_xoa, $nk10 );
teq( 'và vẫn giữ giờ cũ để còn dựng lại được',
	VHCC_DB::giay( '08:45:00' ), $dong_xoa ? (int) $dong_xoa['gio_cu_giay'] : null );

/* ---- lượt BÙ thì cột giờ cũ phải rỗng, không phải 0 ---- */
$nk_bu = array_values( array_filter( VHCC_Bu::ds_nhat_ky( $ADMIN, 'TUTU_BT', substr( $ND, 0, 7 ) ),
	function ( $x ) { return 'bu' === $x['viec']; } ) );
t( 'có lượt bù trong sổ', count( $nk_bu ) > 0 );
teq( 'lượt bù mặc định mang việc "bu"', 'bu', $nk_bu[0]['viec'] );
teq( 'và giờ cũ để rỗng (ô vốn trống)', null, $nk_bu[0]['gio_cu_giay'] );

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
t( 'sửa đè cũng đi qua cổng chung VHCC_Nhan::dat_gio, không tự viết SQL',
	false !== strpos( $than, 'VHCC_Nhan::dat_gio' ) );
/* 🔴 `dat_gio` là cửa DUY NHẤT đè được. Chỉ `VHCC_Bu::sua()` được gọi nó — nơi gác quyền Admin,
   đòi lý do và ghi nhật ký. Có chỗ thứ hai gọi là có đường sửa lương không dấu vết. */
$ai_goi = array();
foreach ( glob( $goc . '/wordpress/vhcp-cham-cong/includes/*.php' ) as $f_dg ) {
	$ma_dg = '';
	foreach ( token_get_all( file_get_contents( $f_dg ) ) as $tk_dg ) {
		if ( is_array( $tk_dg ) && in_array( $tk_dg[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) { continue; }
		$ma_dg .= is_array( $tk_dg ) ? $tk_dg[1] : $tk_dg;
	}
	if ( false !== strpos( $ma_dg, 'VHCC_Nhan::dat_gio(' ) ) { $ai_goi[] = basename( $f_dg ); }
}
teq( '🔴 chỉ ĐÚNG MỘT tệp gọi dat_gio()', array( 'class-vhcc-bu.php' ), $ai_goi );

if ( count( $truot ) ) {
	echo "HỎNG: " . count( $truot ) . "\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "\n✓ SẠCH — $dat phép: bù CHỈ điền ô trống, có nhật ký, không ai tự bù cho mình.\n";
