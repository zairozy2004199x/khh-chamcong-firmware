<?php
/**
 * KIỂM "DỌN CA ĐÊM LẺ" + "SETUP ĐỢI NGÀY RA".
 *
 * ═════════════════════════════════════════════════════════════════════════════════════════════
 * Anh Thắng 23/09/2026 đứng trên bảng SETUP, mở ô `0 ?` ngày 06, gõ giờ ra 11:18 cho giờ vào
 * 19:51 — và bị chối. Hai chuyện lộ ra cùng lúc:
 *   1. dữ liệu cũ (hai hàng thường, mỗi hàng một đầu giờ) không sửa tay được qua form;
 *   2. setup có thể VỀ SAU 6 GIỜ SÁNG — luật lùi ngày tới `demDen` chưa phải là "đợi ngày ra".
 * Bài này canh cả hai, và canh chúng bằng TIỀN: một ca setup phải ra đúng MỘT công đêm.
 *
 * Chạy: php tools/test/kiem-don-dem.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
require_once __DIR__ . '/wp-stub.php';
$goc = dirname( dirname( __DIR__ ) );
vhcc_test_boot( $goc . '/wordpress/vhcp-cham-cong' );

$dat = 0; $truot = array();
function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( null === $them ? '' : ' — ' . ( is_scalar( $them ) ? $them : wp_json_encode( $them, JSON_UNESCAPED_UNICODE ) ) );
}
global $wpdb;
$CHINH = 'VP_DD'; $PHU = 'SETUP_DD';
$wpdb->insert( VHCC_DB::t( 'bo_phan_coso' ), array( 'coso' => $CHINH, 'bo_phan' => 'Văn phòng' ) );
/* Cơ sở phụ CỐ Ý không khai bộ phận — đúng hình dạng sản xuất. */
foreach ( array( array( 'DD1', 'Nguyễn Bá Tuấn', 'Nhân viên' ), array( 'DD2', 'Lê Minh Thiện', 'Nhân viên' ),
	array( 'DDA', 'Sếp Tổng', 'Admin' ) ) as $x ) {
	$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => $x[0], 'ho_ten' => $x[1], 'vai_tro' => $x[2],
		'cua_hang' => $CHINH, 'coso_phu' => $PHU, 'coso_quan' => $CHINH, 'pin_dang_nhap' => '',
		'chuc_vu' => 'NV', 'trang_thai_lam_viec' => 'Đang làm' ) );
}
$AD = array( 'ma_nv' => 'DDA', 'name' => 'Sếp Tổng', 'vai_tro' => 'Admin', 'cua_hang' => $CHINH );
$NV = array( 'ma_nv' => 'DD1', 'name' => 'Nguyễn Bá Tuấn', 'vai_tro' => 'Nhân viên', 'cua_hang' => $CHINH );
VHCC_Luong::dat_ghep( $AD, array( $PHU => $CHINH ) );
t( 'dựng cảnh: cơ sở phụ ghép vào Văn phòng', $CHINH === VHCC_Online::coso_luat( $PHU ) );

$hang = function ( $ngay, $ma, $ht = '' ) use ( $wpdb, $PHU ) {
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . VHCC_DB::t( 'cham_cong' )
		. ' WHERE coso=%s AND ngay=%s AND ma_nv=%s AND hau_to=%s', $PHU, $ngay, $ma, $ht ), ARRAY_A );
};
$g = function ( $s ) { return VHCC_DB::giay( $s ); };

/* ═══════════════════════════════ 1. DỮ LIỆU CŨ: hai hàng thường, mỗi hàng một đầu giờ */
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => $PHU, 'ngay' => '2026-09-06', 'ma_nv' => 'DD1', 'hau_to' => '',
	'ho_ten' => 'Nguyễn Bá Tuấn', 'gio_vao_giay' => $g( '19:51:00' ), 'gio_ra_giay' => null, 'nguon' => 'online',
	'anh_vao' => 'a/vao.jpg', 'vt_vao' => '10.7|106.6|7|0|trong|', 'chuan' => '19:51' ) );
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => $PHU, 'ngay' => '2026-09-07', 'ma_nv' => 'DD1', 'hau_to' => '',
	'ho_ten' => 'Nguyễn Bá Tuấn', 'gio_vao_giay' => $g( '04:02:00' ), 'gio_ra_giay' => null, 'nguon' => 'online',
	'anh_vao' => 'a/ra.jpg', 'vt_vao' => '10.8|106.5|9|0|trong|', 'chuan' => '04:02' ) );
/* Lượt lẻ thật: có vào tối 18/09, hôm sau không ai bấm -> phải để nguyên và kể ra. */
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => $PHU, 'ngay' => '2026-09-18', 'ma_nv' => 'DD2', 'hau_to' => '',
	'ho_ten' => 'Lê Minh Thiện', 'gio_vao_giay' => $g( '18:40:00' ), 'gio_ra_giay' => null, 'nguon' => 'online', 'chuan' => '18:40' ) );
/* Ca NGÀY quên bấm ra (vào 08:00) -> không phải ca đêm, không được ghép với gì. */
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => $PHU, 'ngay' => '2026-09-10', 'ma_nv' => 'DD2', 'hau_to' => '',
	'ho_ten' => 'Lê Minh Thiện', 'gio_vao_giay' => $g( '08:00:00' ), 'gio_ra_giay' => null, 'nguon' => 'online', 'chuan' => '08:00' ) );
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => $PHU, 'ngay' => '2026-09-11', 'ma_nv' => 'DD2', 'hau_to' => '',
	'ho_ten' => 'Lê Minh Thiện', 'gio_vao_giay' => $g( '05:00:00' ), 'gio_ra_giay' => null, 'nguon' => 'online', 'chuan' => '05:00' ) );

/* ── form sửa phải chỉ đúng đường ── */
$r_sua = VHCC_Bu::sua( $AD, array( 'coso' => $PHU, 'ngay' => '2026-09-06', 'ma_nv' => 'DD1',
	'vao' => '19:51', 'ra' => '11:18', 'ly_do' => 'thử chỉ đường' ) );
t( 'form sửa vẫn chối giờ ra sớm hơn giờ vào trên hàng thường', empty( $r_sua['ok'] ), $r_sua );
t( '🔴 và câu báo chỉ tới nút Dọn ca đêm lẻ',
	isset( $r_sua['error'] ) && false !== mb_strpos( $r_sua['error'], 'Dọn ca đêm lẻ' ), $r_sua );

/* ── quyền & phạm vi ── */
$r_nv = VHCC_DonDem::chay( $NV, $PHU, '2026-09', true );
t( '🔴 nhân viên thường KHÔNG dọn được', empty( $r_nv['ok'] ), $r_nv );
$r_kho = VHCC_DonDem::chay( $AD, 'KHO_THEO_GIO', '2026-09', false );
t( 'cơ sở không theo luật Văn phòng -> chối, không bịa hàng ca đêm', empty( $r_kho['ok'] ), $r_kho );

/* ── xem trước: không ghi ── */
$dem = function () use ( $wpdb, $PHU ) {
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'cham_cong' ) . ' WHERE coso=%s', $PHU ) );
};
$truoc = $dem();
$r0 = VHCC_DonDem::chay( $AD, $PHU, '2026-09', false );
t( 'xem trước chạy được', ! empty( $r0['ok'] ), $r0 );
t( '🔴 xem trước KHÔNG ghi gì', $truoc === $dem(), $truoc . ' -> ' . $dem() );
t( '   thấy đúng MỘT cặp ghép được', 1 === count( $r0['cap'] ), $r0['cap'] );
t( '   cặp ấy là 06/09 19:51 → 07/09 04:02',
	$r0['cap'] && '2026-09-06' === $r0['cap'][0]['ngay'] && '19:51' === $r0['cap'][0]['vao']
	&& '2026-09-07' === $r0['cap'][0]['ngaySau'] && '04:02' === $r0['cap'][0]['ra'], $r0['cap'] );
t( '🔴 lượt lẻ 18/09 được KỂ RA, không bị ghép bừa',
	(bool) array_filter( $r0['le'], function ( $x ) { return '2026-09-18' === $x['ngay']; } ), $r0['le'] );
t( '🔴 ca ngày quên bấm ra (vào 08:00) KHÔNG bị coi là ca đêm',
	(bool) array_filter( $r0['le'], function ( $x ) { return '2026-09-10' === $x['ngay'] && false !== mb_strpos( $x['lyDo'], 'ca ngày' ); } ), $r0['le'] );

/* ── dọn thật ── */
$r1 = VHCC_DonDem::chay( $AD, $PHU, '2026-09', true );
t( 'dọn thật chạy được', ! empty( $r1['ok'] ) && 1 === (int) $r1['da_ghi'], $r1 );
$cd = $hang( '2026-09-06', 'DD1', 'CD' );
t( '🔴 có hàng ca đêm 06/09 đủ cặp', $cd && '19:51:00' === VHCC_DB::hhmmss( $cd['gio_vao_giay'] )
	&& '04:02:00' === VHCC_DB::hhmmss( $cd['gio_ra_giay'] ), $cd );
t( '   giờ ra trên trục phẳng (> 24h)', $cd && (int) $cd['gio_ra_giay'] > 86400, $cd );
t( '🔴 hai hàng thường cũ đã gỡ', null === $hang( '2026-09-06', 'DD1' ) && null === $hang( '2026-09-07', 'DD1' ) );
t( '   ảnh/vị trí đi theo giờ: ảnh vào từ hàng 1, ảnh ra từ hàng 2',
	$cd && 'a/vao.jpg' === $cd['anh_vao'] && 'a/ra.jpg' === $cd['anh_ra'] && '' !== $cd['vt_ra'], $cd );
t( '   lượt lẻ 18/09 vẫn nguyên', null !== $hang( '2026-09-18', 'DD2' ) );
t( '   ca ngày 10/09 vẫn nguyên', null !== $hang( '2026-09-10', 'DD2' ) && null !== $hang( '2026-09-11', 'DD2' ) );
$ky = VHCC_DB::rows( $wpdb->prepare( 'SELECT * FROM ' . VHCC_DB::t( 'cham_bu' ) . ' WHERE coso=%s AND viec=%s', $PHU, 'don' ) );
t( '🔴 có nhật ký cũ→mới (3 dòng: vào, ra, lượt bị chuyển)', 3 === count( $ky ), count( $ky ) );
/* Engine (26/09/2026: công đêm tính ngay ở NGÀY VÀO): đêm 06 cho 1 công đêm ngay tại 06, còn
   công bù của nó mới rơi vào 07 trên bảng ghép. */
$bl = VHCC_Luong::vp_bang_cong_va_luong( $CHINH, '2026-09' );
$d06 = null; $d07 = null;
foreach ( (array) $bl['detail'] as $x ) {
	if ( 'DD1' !== $x['ma'] ) { continue; }
	if ( '2026-09-06' === $x['ngay'] ) { $d06 = $x; }
	if ( '2026-09-07' === $x['ngay'] ) { $d07 = $x; }
}
t( '🔴 sau khi dọn, đêm 06 ra đúng 1 CÔNG ĐÊM ngay tại ngày 06', $d06 && 1.0 === (float) $d06['congDem'], $d06 );
t( '   và 1 CÔNG BÙ rơi vào ngày 07 (hôm sau)', $d07 && 1.0 === (float) $d07['congBu']
	&& '2026-09-06' === (string) $d07['buTuNgay'], $d07 );
/* Chạy lại: không còn gì để dọn, không đẻ thêm nhật ký. */
$n_ky = count( $ky );
$r2 = VHCC_DonDem::chay( $AD, $PHU, '2026-09', true );
t( '🔴 dọn lại lần hai -> 0 cặp, không đẻ thêm nhật ký', 0 === (int) $r2['da_ghi']
	&& $n_ky === count( VHCC_DB::rows( $wpdb->prepare( 'SELECT id FROM ' . VHCC_DB::t( 'cham_bu' ) . ' WHERE coso=%s AND viec=%s', $PHU, 'don' ) ) ), $r2 );
/* Ngày đã có hàng -CD -> không nhét đè. */
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => $PHU, 'ngay' => '2026-09-20', 'ma_nv' => 'DD1', 'hau_to' => '',
	'ho_ten' => 'Nguyễn Bá Tuấn', 'gio_vao_giay' => $g( '19:00:00' ), 'gio_ra_giay' => null, 'nguon' => 'online', 'chuan' => '19:00' ) );
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => $PHU, 'ngay' => '2026-09-21', 'ma_nv' => 'DD1', 'hau_to' => '',
	'ho_ten' => 'Nguyễn Bá Tuấn', 'gio_vao_giay' => $g( '03:00:00' ), 'gio_ra_giay' => null, 'nguon' => 'online', 'chuan' => '03:00' ) );
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => $PHU, 'ngay' => '2026-09-20', 'ma_nv' => 'DD1', 'hau_to' => 'CD',
	'ho_ten' => 'Nguyễn Bá Tuấn', 'gio_vao_giay' => $g( '21:00:00' ), 'gio_ra_giay' => 86400 + $g( '02:00:00' ), 'nguon' => 'sua', 'chuan' => '21:00 02:00' ) );
$r3 = VHCC_DonDem::chay( $AD, $PHU, '2026-09', true );
t( '🔴 ngày đã có hàng ca đêm -> để nguyên, kể ra', 0 === (int) $r3['da_ghi']
	&& (bool) array_filter( $r3['le'], function ( $x ) { return '2026-09-20' === $x['ngay'] && false !== mb_strpos( $x['lyDo'], 'ĐÃ có' ); } ), $r3 );

/* ═══════════════════════════════ 2. "SETUP ĐỢI NGÀY RA" — về sau 6h sáng vẫn nối được */
$U1 = array( 'ma_nv' => 'DD1', 'ho_ten' => 'Nguyễn Bá Tuấn', 'coso' => $CHINH, 'pin' => '' );
$k = VHCC_Online::cham_cong( $U1, '', null, $PHU, '', strtotime( '2026-09-12 19:51:00 UTC' ), 0 );
t( 'bấm vào 19:51 (12/09) -> mở hàng ca đêm', ! empty( $k['ok'] ) && 'DD1-CD' === $k['ma'] && '2026-09-12' === $k['ngay'], $k );
$k = VHCC_Online::cham_cong( $U1, '', null, $PHU, '', strtotime( '2026-09-13 11:18:00 UTC' ), 0 );
t( '🔴 11:18 hôm sau (SAU demDen 06:00) vẫn là giờ RA của ca 12/09',
	! empty( $k['ok'] ) && 'ra' === $k['loai'] && '2026-09-12' === $k['ngay'], $k );
$h12 = $hang( '2026-09-12', 'DD1', 'CD' );
t( '   hàng 12/09: 19:51 → 11:18', $h12 && '11:18:00' === VHCC_DB::hhmmss( $h12['gio_ra_giay'] ) && (int) $h12['gio_ra_giay'] > 86400, $h12 );
t( '   KHÔNG đẻ hàng thường "vào 11:18" ở 13/09', null === $hang( '2026-09-13', 'DD1' ) );
/* Engine: ca 19:51 → 11:18 trùm qua khung đêm -> là ca ĐÊM, không phải "ca lạ"; 1 công đêm ngay
   tại 12/09 (ngày vào), công bù mới rơi vào 13/09. */
$bl = VHCC_Luong::vp_bang_cong_va_luong( $CHINH, '2026-09' );
$d12 = null; $d13 = null;
foreach ( (array) $bl['detail'] as $x ) { if ( 'DD1' !== $x['ma'] ) { continue; } if ( '2026-09-12' === $x['ngay'] ) { $d12 = $x; } if ( '2026-09-13' === $x['ngay'] ) { $d13 = $x; } }
t( '🔴 ca 19:51→11:18 KHÔNG bị coi là ca lạ', $d12 && empty( $d12['caLa'] ), $d12 );
t( '🔴 và cho đúng 1 CÔNG ĐÊM ngay tại 12/09', $d12 && 1.0 === (float) $d12['congDem'], $d12 );
t( '   và 1 CÔNG BÙ rơi vào 13/09', $d13 && 1.0 === (float) $d13['congBu'], $d13 );
/* Chiều chặn: hôm sau bấm SAU giờ tan ca là MỞ ca mới, không đóng ca cũ. */
VHCC_Online::cham_cong( $U1, '', null, $PHU, '', strtotime( '2026-09-15 19:51:00 UTC' ), 0 );
$k = VHCC_Online::cham_cong( $U1, '', null, $PHU, '', strtotime( '2026-09-16 18:00:00 UTC' ), 0 );
t( '🔴 hôm sau 18:00 (sau ngayDen) -> MỞ ca đêm mới ở 16/09, không đóng ca 15/09',
	! empty( $k['ok'] ) && 'vao' === $k['loai'] && '2026-09-16' === $k['ngay'] && 'DD1-CD' === $k['ma'], $k );
/* Chiều chặn quan trọng nhất: ở cơ sở CHÍNH, nhân viên văn phòng có hàng đêm đang mở mà sáng
   bấm 08:30 -> đó là VÀO ca ngày, tuyệt đối không đóng hàng đêm. */
$U2 = array( 'ma_nv' => 'DD2', 'ho_ten' => 'Lê Minh Thiện', 'coso' => $CHINH, 'pin' => '' );
VHCC_Online::cham_cong( $U2, '', null, $CHINH, '', strtotime( '2026-09-22 18:30:00 UTC' ), 0 );   // tăng ca, mở -CD
$k = VHCC_Online::cham_cong( $U2, '', null, $CHINH, '', strtotime( '2026-09-23 08:30:00 UTC' ), 0 );
t( '🔴 văn phòng: 08:30 sáng là VÀO ca ngày, không đóng hàng đêm hôm trước',
	! empty( $k['ok'] ) && 'DD2' === $k['ma'] && '2026-09-23' === $k['ngay'] && 'vao' === $k['loai'], $k );

/* ═══════════════════════════════ 3. vp_ca_hang2: ca trùm khung đêm là ca đêm */
$cfg = VHCC_Luong::vp_cfg( $CHINH );
t( 'ca 19:51 → 11:18 (trùm khung) = đêm', 'dem' === VHCC_Luong::vp_ca_hang2( $cfg, $g( '19:51:00' ), 86400 + $g( '11:18:00' ) )['loai'] );
t( 'ca 20:00 → 07:00 (hai đầu đều ngoài khung) = đêm', 'dem' === VHCC_Luong::vp_ca_hang2( $cfg, $g( '20:00:00' ), 86400 + $g( '07:00:00' ) )['loai'] );
t( 'tăng ca 17:30 → 20:30 vẫn là tăng ca', 'tangca' === VHCC_Luong::vp_ca_hang2( $cfg, $g( '17:30:00' ), $g( '20:30:00' ) )['loai'] );
t( 'giờ ca ngày lọt hàng 2 (09:00 → 12:00) vẫn là ca lạ', 'la' === VHCC_Luong::vp_ca_hang2( $cfg, $g( '09:00:00' ), $g( '12:00:00' ) )['loai'] );

echo "\n";
if ( $truot ) {
	echo 'TRƯỢT ' . count( $truot ) . ":\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — ca setup chẻ đôi được ghép lại, và ca về muộn vẫn ra đúng một công đêm.\n";
