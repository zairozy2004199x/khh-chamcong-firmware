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

/* 🔴 NGƯỜI BÙ / SỬA NAY LÀ **KẾ TOÁN** — 18/09/2026.
   Anh Thắng: *"Cửa hàng trưởng không được bù giờ công, nếu thiếu thì chỗ file excel"* và
   *"cửa hàng trưởng không được sửa công nữa mà theo người được chỉ định bật quyền mới được
   sửa thôi"*. `cham_bu` và `sua_gio` cùng lên bậc Kế toán (xem `VHCC_Vai::QUYEN`).
   ⚠️ `$CHT` VẪN Ở LẠI, và có việc riêng: nó là người phải bị CHỐI. Xoá nó đi rồi đổi hết sang
      Kế toán là bộ thử thôi canh mất cái cửa vừa đóng — mà đóng cửa mới là thứ bản này làm.
   ⚠️ VÌ SAO KHÔNG LÁI BẰNG ADMIN CHO NHANH: `han_ngay()` chỉ tha người có `cong_tat_ca`, và
      cả tệp này bù/sửa vào HÔM QUA. Kế toán có `cong_tat_ca` nên qua được, đồng thời vẫn là
      bậc THẤP NHẤT làm được việc này — tức nó đo đúng mép của cái cửa, không đo thừa. */
$CHT   = array( 'name' => 'Anh CHT',  'role' => 'Cửa hàng trưởng', 'coso' => 'TUTU_BT', 'ma_nv' => 'NVCHT' );
$KT    = array( 'name' => 'Chị KT',   'role' => 'Kế toán',         'coso' => 'TUTU_BT', 'ma_nv' => 'NVKT' );
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
	empty( $r['ok'] ) && false !== strpos( $r['error'], 'Kế toán' ), $r );
teq( 'và KHÔNG có hàng nào được tạo', null, hang() );

/* 🔴 VÀ CỬA HÀNG TRƯỞNG CŨNG KHÔNG — cửa vừa đóng 18/09/2026, phải có phép canh nó.
   Câu chối phải nói đúng bậc còn thiếu, kẻo người ta đi xin nhầm Admin. */
$r = bu( $CHT );
t( '🔴 Cửa hàng trưởng KHÔNG bù được nữa',
	empty( $r['ok'] ) && false !== strpos( $r['error'], 'Kế toán' ), $r );
teq( 'và cũng KHÔNG tạo hàng nào', null, hang() );
/* Đường thay thế: chỉ định từng người bằng một dòng `nv:<Mã NV>`, không phải hạ bậc cả lớp. */
VHCC_Vai::dat_ngoai_le( $ADMIN, 'nv:NVCHT', 'cham_bu', 'mo' );
$r = bu( $CHT );
t( '🔴 nhưng CHỈ ĐỊNH riêng cho đúng người ấy thì bù được', ! empty( $r['ok'] ), $r );
VHCC_Vai::dat_ngoai_le( $ADMIN, 'nv:NVCHT', 'cham_bu', '' );
/* Dọn lại hàng vừa bù — mấy phép dưới đòi bảng còn trắng ở ngày ấy. */
$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'cham_cong' ) );
$wpdb->query( 'DELETE FROM ' . VHCC_DB::t( 'cham_bu' ) );
t( 'gỡ dòng chỉ định thì đóng lại ngay', ! VHCC_Vai::duoc( $CHT, 'cham_bu' ) );

/* 🔴 Chốt nặng nhất của lớp này: bù công là đổi thẳng ra tiền, nên không ai tự ký duyệt tiền
   của mình được — kể cả Admin. */
$r = bu( $KT, array( 'ma_nv' => 'NVKT' ) );
t( 'Kế toán KHÔNG tự bù cho mình',
	empty( $r['ok'] ) && false !== strpos( $r['error'], 'tự bù' ), $r );
$r = bu( $ADMIN, array( 'ma_nv' => 'NVAD' ) );
t( 'ADMIN cũng KHÔNG tự bù cho mình', empty( $r['ok'] ), $r );
/* Hậu tố không được dùng để lách: NVCHT-CD vẫn là NVCHT. */
$r = bu( $KT, array( 'ma_nv' => 'NVKT-CD' ) );
t( 'thêm hậu tố -CD cũng không lách được chốt tự bù', empty( $r['ok'] ), $r );

$r = bu( $KT, array( 'ma_nv' => 'XX999' ) );
t( 'mã KHÔNG có hồ sơ -> chối (bù cho người không có là tạo công ma)',
	empty( $r['ok'] ) && false !== strpos( $r['error'], 'hồ sơ' ), $r );

VHCC_NhanSu::$co_quyen = false;
$r = bu( $KT );
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
$r = bu( $KT, array( 'ngay' => $mai ) );
t( 'bù cho ngày mai bị chối ở cửa ghi', empty( $r['ok'] ), $r );

// ============================================================ 3. LÝ DO
echo "— lý do —\n";
foreach ( array( '', '   ', 'ok', 'quên' ) as $ly ) {
	$r = bu( $KT, array( 'ly_do' => $ly ) );
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
$r = bu( $KT, array( 'vao' => '', 'ra' => '' ) );
t( 'không nhập giờ nào -> chối', empty( $r['ok'] ), $r );
$r = bu( $KT, array( 'vao' => '17:00', 'ra' => '08:00' ) );
t( 'giờ ra sớm hơn giờ vào -> chối, và chỉ sang hàng ca đêm',
	empty( $r['ok'] ) && false !== strpos( $r['error'], '-CD' ), $r );

// ============================================================ 5. BÙ ĐƯỢC
echo "— bù được —\n";
$r = bu( $KT );
t( 'Kế toán bù được cho nhân viên', ! empty( $r['ok'] ), $r );
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
teq( 'nhật ký nhớ AI bù', 'Chị KT', $o['vao']['nguoi_bu'] );
teq( 'nhớ mã người bù',   'NVKT',   $o['vao']['ma_nguoi_bu'] );
teq( 'nhớ VAI của người bù', VHCC_Vai::KE_TOAN, $o['vao']['vai_nguoi_bu'] );
t( 'nhớ lý do', false !== strpos( (string) $o['vao']['ly_do'], 'máy hỏng' ) );
t( 'nhớ lúc bù', '' !== trim( (string) $o['vao']['tao_luc'] ) );

// ============================================================ 6. KHÔNG ĐÈ
echo "— không đè lên giờ đã có —\n";
/* Máy đã ghi một ngày đủ cặp -> bù KHÔNG được đụng vào. */
VHCC_Nhan::ghi_gio( 'TUTU_BT', $hom_nay, 'NV002', 'Người NV002', VHCC_DB::giay( '07:55:00' ), '', 'may' );
VHCC_Nhan::ghi_gio( 'TUTU_BT', $hom_nay, 'NV002', 'Người NV002', VHCC_DB::giay( '16:40:00' ), '', 'may' );
$r = bu( $KT, array( 'ngay' => $hom_nay, 'ma_nv' => 'NV002', 'vao' => '06:00', 'ra' => '23:00' ) );
t( 'ngày đã đủ giờ -> chối hẳn, và chỉ sang gắn cờ',
	empty( $r['ok'] ) && false !== strpos( $r['error'], 'gắn cờ' ), $r );
$h2 = hang( 'NV002', $hom_nay );
teq( 'giờ vào của MÁY còn nguyên', VHCC_DB::giay( '07:55:00' ), (int) $h2['gio_vao_giay'] );
teq( 'giờ ra của MÁY còn nguyên',  VHCC_DB::giay( '16:40:00' ), (int) $h2['gio_ra_giay'] );
teq( 'nguồn vẫn là "may", không bị bù nhuộm sang',  'may', $h2['nguon'] );

/* Thiếu MỖI giờ ra -> bù điền được đúng ô trống, ô đã có thì bỏ qua VÀ NÓI RA. */
VHCC_Nhan::ghi_gio( 'TUTU_BT', $hom_nay, 'NV003', 'Người NV003', VHCC_DB::giay( '08:10:00' ), '', 'may' );
$r = bu( $KT, array( 'ngay' => $hom_nay, 'ma_nv' => 'NV003', 'vao' => '06:00', 'ra' => '17:30' ) );
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
$r = bu( $KT, array( 'ngay' => $hom_nay, 'ma_nv' => 'NV003-CD', 'vao' => '22:00', 'ra' => '23:30' ) );
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
/* 🔴 SỬA ĐÈ NAY LÀ BẬC KẾ TOÁN — anh Thắng 18/09/2026: *"cơ chế hiện tại là cửa hàng trưởng
   không được sửa công nữa mà theo người được chỉ định bật quyền mới được sửa thôi"*.
   LỊCH SỬ, để khỏi ai đào lại: 26/08 Admin → 28/08 hạ xuống Cửa hàng trưởng → 18/09 nâng lên
   Kế toán + chỉ định từng người. Bậc này đã đổi ba lần; mấy chốt PHẠM VI bên dưới thì chưa đổi
   lần nào, và đó mới là thứ tệp này canh. */
$r = sua( $CHT, array( 'vao' => '09:00' ) );
t( '🔴 Cửa hàng trưởng KHÔNG còn sửa đè được',
	empty( $r['ok'] ) && false !== strpos( $r['error'], 'Kế toán' ), $r );
teq( 'và giờ cũ không suy suyển', VHCC_DB::giay( '08:00:00' ),
	(int) hang( 'NV009' )['gio_vao_giay'] );
/* Chối xong phải chỉ đường — gắn cờ là việc họ VẪN làm được, và nó không đè lên giờ máy ghi. */
t( 'và chỉ đúng đường còn lại: gắn cờ để cấp trên sửa',
	false !== strpos( $r['error'], 'gắn cờ' ), $r );

$r = sua( $KT, array( 'vao' => '09:00' ) );
t( '🔴 Kế toán sửa đè được giờ của cơ sở mình', ! empty( $r['ok'] ), $r );
teq( 'và giờ mới vào đúng ô', VHCC_DB::giay( '09:00:00' ), (int) hang( 'NV009' )['gio_vao_giay'] );
/* Trả lại cảnh cũ cho những phép thử phía dưới. */
sua( $ADMIN, array( 'vao' => '08:00' ) );
teq( 'trả lại giờ cũ để chạy tiếp', VHCC_DB::giay( '08:00:00' ),
	(int) hang( 'NV009' )['gio_vao_giay'] );

/* 🔴 CƠ SỞ KHÁC THÌ VẪN CHỐI — chốt PHẠM VI, không phải chốt bậc. Mất nó là một người sửa
   được bảng công của 25 cửa hàng kia. */
$r_xa = VHCC_Bu::sua( $KT, array( 'coso' => 'JP_HCM', 'ngay' => $ND, 'ma_nv' => 'NV009',
	'vao' => '09:00', 'ly_do' => 'thử sửa sang cơ sở khác' ) );
t( '🔴 nhưng KHÔNG sửa được cơ sở khác', empty( $r_xa['ok'] ), $r_xa );
/* 🔴 VẪN BẮT GHI VÌ SAO — lý do người ta gõ vào là thứ duy nhất còn tra ngược được. */
$r_kolydo = VHCC_Bu::sua( $KT, array( 'coso' => 'TUTU_BT', 'ngay' => $ND, 'ma_nv' => 'NV009',
	'vao' => '09:00', 'ly_do' => '' ) );
t( '🔴 và vẫn bắt ghi vì sao', empty( $r_kolydo['ok'] ), $r_kolydo );

/* 🔴 CHỈ ĐỊNH RIÊNG CHO MỘT NGƯỜI — đường thay cho việc hạ bậc cả lớp.
   Khai một dòng `nv:<Mã NV> · sua_gio · mo` là đúng một cửa hàng trưởng có tên sửa được, có
   chỗ soát lại, gỡ bằng một dòng. Đường này hỏng thì cách duy nhất còn lại là nâng vai cho
   toàn bộ cửa hàng trưởng — lặng lẽ, và không ai thấy gì đổi.
   ⚠️ Người được chỉ định VẪN chịu chốt HẠN NGÀY (`han_ngay`, gác bằng `cong_tat_ca`), nên họ
      chỉ sửa được HÔM NAY. Cả khối này bù/sửa vào HÔM QUA, nên phép dưới dựng riêng một ngày
      hôm nay — dùng chung ngày là nó đỏ vì hạn chứ không phải vì quyền. */
VHCC_Nhan::ghi_gio( 'TUTU_BT', $hom_nay, 'NV011', 'Người NV011', VHCC_DB::giay( '08:00:00' ), '', 'may' );
VHCC_Vai::dat_ngoai_le( $ADMIN, 'nv:' . $CHT['ma_nv'], 'sua_gio', 'mo' );
$r_cd = VHCC_Bu::sua( $CHT, array( 'coso' => 'TUTU_BT', 'ngay' => $hom_nay, 'ma_nv' => 'NV011',
	'vao' => '09:15', 'ly_do' => 'được chỉ định, sửa giờ hôm nay' ) );
t( '🔴 cửa hàng trưởng ĐƯỢC CHỈ ĐỊNH thì sửa được', ! empty( $r_cd['ok'] ), $r_cd );
teq( 'và giờ mới vào đúng ô',
	VHCC_DB::giay( '09:15:00' ), (int) hang( 'NV011', $hom_nay )['gio_vao_giay'] );
/* 🔴 NHƯNG HẠN NGÀY KHÔNG ĐI THEO DÒNG CHỈ ĐỊNH. Chỉ định mở đúng đầu việc `sua_gio`; hạn
   ngày gác bằng một quyền KHÁC (`cong_tat_ca`) mà người này vẫn không có. Mất chốt ấy là một
   người được chỉ định sửa ngược được cả những tháng đã chốt lương. */
$r_cu = sua( $CHT, array( 'vao' => '09:45' ) );          // $ND = hôm qua
t( '🔴 được chỉ định vẫn KHÔNG sửa ngược được ngày cũ',
	empty( $r_cu['ok'] ) && ! empty( $r_cu['quaHan'] ), $r_cu );
teq( 'và giờ hôm qua còn nguyên', VHCC_DB::giay( '08:00:00' ),
	(int) hang( 'NV009' )['gio_vao_giay'] );

/* 🔴 VÀ DÒNG `khoa` THẮNG DÒNG `mo` — chốt này là đường THU quyền của một người cụ thể, kể cả
   khi bậc của họ vốn đủ. Không có nó thì muốn chặn một người phải hạ vai họ xuống. */
VHCC_Vai::dat_ngoai_le( $ADMIN, 'nv:' . $KT['ma_nv'], 'sua_gio', 'khoa' );
$r_khoa = sua( $KT, array( 'vao' => '09:30' ) );
t( '🔴 khoá riêng sua_gio thì người ấy không sửa được nữa, dù đủ bậc',
	empty( $r_khoa['ok'] ), $r_khoa );
teq( 'và giờ cũ còn nguyên', VHCC_DB::giay( '08:00:00' ),
	(int) hang( 'NV009' )['gio_vao_giay'] );
VHCC_Vai::dat_ngoai_le( $ADMIN, 'nv:' . $KT['ma_nv'], 'sua_gio', '' );
VHCC_Vai::dat_ngoai_le( $ADMIN, 'nv:' . $CHT['ma_nv'], 'sua_gio', '' );
/* ⚠️ Sửa sang một giờ KHÁC giờ đang có: `VHCC_Bu::sua` coi "không đổi gì" là không có việc để
   làm, nên đặt lại đúng 08:00 thì nó chối — mà chối ấy không nói gì về khoá quyền. */
$r_mo = sua( $KT, array( 'vao' => '08:15' ) );
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

/* ⚠️ `8h30` KHÔNG CÒN LÀ "SAI DẠNG" TỪ 3.65.0 — và đó là chủ ý, không phải nới lỏng.
   `VHCC_DB::gio_24()` nhận `8h30`, `08.30`, `0830`, `830` vì đó đúng là những kiểu người ta gõ
   thật trên bàn phím số. Bài thử này viết TRƯỚC lúc ấy nên còn dùng `8h30` làm mẫu "sai dạng";
   để nguyên thì nó canh một luật đã bị thay, tức là canh cho một bản cũ không còn tồn tại.
   Nay canh đúng hai việc: kiểu gõ nhanh phải ĂN, còn chuỗi thật sự vô nghĩa phải bị CHỐI. */
$r = sua( $ADMIN, array( 'vao' => '8h30' ) );
t( 'kiểu gõ nhanh "8h30" được nhận', ! empty( $r['ok'] ), $r );
teq( 'và hiểu đúng thành 08:30', VHCC_DB::giay( '08:30:00' ), (int) hang( 'NV009' )['gio_vao_giay'] );
$r = sua( $ADMIN, array( 'vao' => '09:30' ) );   // trả lại mốc cũ cho mấy phép dưới
t( 'trả lại 09:30 được', ! empty( $r['ok'] ), $r );

$r = sua( $ADMIN, array( 'vao' => '8 giờ rưỡi' ) );
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
/* 🔴 LỚP NÀY NAY CÓ MỘT CÂU `delete` — VÀ ĐÚNG MỘT.
   `VHCC_Bu::xoa()` xoá hẳn một DÒNG chấm công (máy quẹt nhầm mặt thì có một ngày công không
   có thật). Phép cũ hỏi "không có chữ DELETE nào ở đâu cả" nên nó đỏ ngay khi tính năng ấy ra
   đời — mà thứ nó thật sự canh không phải chữ DELETE, mà là: **SỔ KHÔNG XOÁ ĐƯỢC**.
   Nên ba phép dưới hỏi đúng ba vế của điều ấy:
     · không câu xoá/sửa nào chạm bảng nhật ký `cham_bu`;
     · câu xoá duy nhất là lên bảng `cham_cong`, và chỉ có MỘT;
     · sổ được GHI TRƯỚC khi xoá — xoá trước ghi sau thì sổ hỏng là dòng mất mà không còn gì
       nói nó từng tồn tại (xem khối chú thích ở `xoa()`). */
t( '🔴 KHÔNG câu xoá nào chạm bảng nhật ký',
	false === stripos( $than, "delete( VHCC_DB::t( 'cham_bu'" ), 'có xoá nhật ký' );
t( 'và không có câu UPDATE nào lên nhật ký',
	false === strpos( $than, "update( VHCC_DB::t( 'cham_bu'" ) );
teq( '🔴 chỉ ĐÚNG MỘT câu xoá trong cả lớp', 1,
	preg_match_all( '/\$wpdb->delete\(/', $than ) );
t( 'và nó xoá DÒNG CHẤM CÔNG, không phải thứ gì khác',
	false !== strpos( $than, "delete( VHCC_DB::t( 'cham_cong' )" ), 'xoá nhầm bảng' );
/* Ghi sổ trước, xoá sau — đo bằng vị trí trong thân hàm `xoa()`. */
$than_xoa = strstr( strstr( $than, 'public static function xoa(' ), "\$wpdb->delete(", true );
t( '🔴 sổ được ghi TRƯỚC câu xoá, không phải sau',
	is_string( $than_xoa ) && false !== strpos( $than_xoa, "self::nhat_ky(" ), 'xoá trước ghi sau' );
/* Và hỏi thẳng hành vi, đừng chỉ đọc mã: xoá xong sổ phải CÒN, kèm giờ cũ để dựng lại được. */
VHCC_Nhan::ghi_gio( 'TUTU_BT', $hom_nay, 'NV012', 'Người NV012', VHCC_DB::giay( '08:20:00' ), '', 'may' );
$r_xoa = VHCC_Bu::xoa( $KT, array( 'coso' => 'TUTU_BT', 'ngay' => $hom_nay, 'ma_nv' => 'NV012',
	'ly_do' => 'máy chấm nhầm sang mã người khác' ) );
t( 'xoá được một dòng chấm nhầm', ! empty( $r_xoa['ok'] ), $r_xoa );
teq( 'và dòng ấy hết sạch khỏi bảng chấm công', null, hang( 'NV012', $hom_nay ) );
$nk_xoa = array_values( array_filter( VHCC_Bu::ds_nhat_ky( $ADMIN, 'TUTU_BT', substr( $hom_nay, 0, 7 ) ),
	function ( $x ) { return 'NV012' === $x['ma_nv']; } ) );
t( '🔴 nhưng SỔ VẪN CÒN — bằng chứng không được biến mất theo', count( $nk_xoa ) > 0, $nk_xoa );
teq( 'sổ ghi rõ đây là lượt XOÁ', 'xoa', $nk_xoa[0]['viec'] );
$co_gio_cu = false;
foreach ( $nk_xoa as $x_nk ) {
	if ( VHCC_DB::giay( '08:20:00' ) === (int) $x_nk['gio_cu_giay'] ) { $co_gio_cu = true; }
}
t( '🔴 và giữ GIỜ CŨ để còn dựng lại được', $co_gio_cu, $nk_xoa );
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
