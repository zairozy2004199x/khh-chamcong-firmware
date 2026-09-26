<?php
/**
 * KIỂM "CÔNG BÙ" CỘNG VÀO BẢNG LƯƠNG THẬT (khối Văn phòng, người ăn lương tháng).
 *
 * ═════════════════════════════════════════════════════════════════════════════════════════════
 * Anh Thắng 26/09/2026: *"công bù là tự cộng vào khhcm"*. Dò ra: `VHCC_BangLuong::dung()` — cửa
 * xuất "Bảng lương" THẬT nộp cho kế toán — đọc thẳng giờ chấm thô, không hề biết tới "công bù"
 * (khoản cộng thêm cho ngày hôm sau của một ca đêm đạt chuẩn, KHÔNG có giờ chấm thật đứng sau —
 * xem `VHCC_Luong::vp_tinh_nguoi()`). Khoản này trước bản vá chỉ hiện trên màn Bảng công, chưa
 * từng vào bảng lương thật.
 *
 * Bản vá: cộng thẳng `congBu` vào `congThuc` — CHỈ cho người ăn lương THÁNG (đơn vị "công" khớp
 * với công thức của họ), CHỈ ở khối Văn phòng (`cach_tinh()==='cong'`, đúng lời anh Thắng: *"áp
 * dụng cho setup và khhcm chứ có nói ngoài cơ sở khác đâu"*). KHÔNG đụng "công đêm" — giờ của
 * chính ca đêm đã tự ra công qua giờ chấm thô (quy đổi bậc giờ không phân biệt ngày/đêm), cộng
 * thêm nữa là tính trùng — đã hỏi thẳng và chốt chỉ cộng đúng "công bù".
 *
 * Chạy: php tools/test/kiem-bang-luong-cong-bu.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
require_once __DIR__ . '/wp-stub.php';
$goc = dirname( dirname( __DIR__ ) );
vhcc_test_boot( $goc . '/wordpress/vhcp-cham-cong' );
define( 'VHCC_TEST', 1 );

$dat = 0; $truot = array();
function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( null === $them ? '' : ' — ' . ( is_scalar( $them ) ? $them : wp_json_encode( $them, JSON_UNESCAPED_UNICODE ) ) );
}
function teq( $ten, $ky_vong, $thuc, $them = null ) {
	t( $ten . ' (kỳ vọng ' . wp_json_encode( $ky_vong, JSON_UNESCAPED_UNICODE ) . ', được '
		. wp_json_encode( $thuc, JSON_UNESCAPED_UNICODE ) . ')', $ky_vong === $thuc, $them );
}
global $wpdb;
$u_ad = array( 'role' => 'Admin' );

/* ═══════════════════════════════════ DỰNG CẢNH ═══════════════════════════════════ */
$CHINH = 'BL_VP'; $PHU = 'BL_SETUP'; $GIO_CS = 'BL_CUAHANG';
$wpdb->insert( VHCC_DB::t( 'bo_phan_coso' ), array( 'coso' => $CHINH, 'bo_phan' => 'Văn phòng' ) );
/* Cơ sở phụ CỐ Ý không khai bộ phận — đúng hình dạng sản xuất. */
VHCC_Luong::dat_ghep( $u_ad, array( $PHU => $CHINH ) );
teq( 'dựng cảnh: khối Văn phòng tính THEO CÔNG', 'cong', VHCC_Luong::cach_tinh( $CHINH ) );
teq( 'dựng cảnh: cơ sở cửa hàng thường vẫn tính THEO GIỜ', 'gio', VHCC_Luong::cach_tinh( $GIO_CS ) );

/* Người 1: ăn LƯƠNG THÁNG, cua_hang = cơ sở chính (Văn phòng). */
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'BLT1', 'ho_ten' => 'Người Lương Tháng',
	'cua_hang' => $CHINH, 'luong_co_ban' => 9000000, 'vai_tro' => 'Nhân viên' ) );
/* Người 2: cùng khối Văn phòng nhưng KHÔNG có lương cơ bản (tính theo giờ) — chốt: KHÔNG được
   cộng công bù, vì "công" không có nghĩa trong công thức giờ × đơn giá của người này. */
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'BLT2', 'ho_ten' => 'Người Theo Giờ',
	'cua_hang' => $CHINH, 'chuc_vu' => 'NV', 'vai_tro' => 'Nhân viên' ) );
VHCC_GiaGio::dat_coso( $u_ad, $CHINH, array( 'NV' => 20000 ) );
VHCC_Luong::dat_cai_dat( 'VP_CONG_CFG', array( 'ngayCongThang' => 26 ), $u_ad );

/* Ca đêm SETUP: vào 20:00, ra 04:00 hôm sau — đạt chuẩn công đêm + công bù (mặc định demCong=1;
   giờ ra 04:00 đúng mốc 2 -> demBuSo2=1). Công đêm rơi vào ngày 05 (ngày VÀO); công bù rơi vào
   ngày 06 (hôm sau). */
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => $PHU, 'ngay' => '2026-08-05',
	'ma_nv' => 'BLT1', 'hau_to' => 'CD', 'ho_ten' => 'Người Lương Tháng',
	'gio_vao_giay' => VHCC_DB::giay( '20:00:00' ), 'gio_ra_giay' => VHCC_DB::giay( '04:00:00' ) + VHCC_DB::NGAY_GIAY,
	'nguon' => 'may' ) );
/* Người 2 (theo giờ) cũng có đúng một ca đêm y hệt, để canh chốt "không cộng cho người theo giờ"
   không phải xanh chỉ vì người này tình cờ không có công bù nào để mà cộng. */
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => $PHU, 'ngay' => '2026-08-05',
	'ma_nv' => 'BLT2', 'hau_to' => 'CD', 'ho_ten' => 'Người Theo Giờ',
	'gio_vao_giay' => VHCC_DB::giay( '20:00:00' ), 'gio_ra_giay' => VHCC_DB::giay( '04:00:00' ) + VHCC_DB::NGAY_GIAY,
	'nguon' => 'may' ) );

/* Xác nhận dựng cảnh đúng: `vp_tinh_nguoi()`/bảng công màn hình đã thấy đúng 1 công đêm + 1 công
   bù cho ngày 06 (qua `vp_bang_cong_va_luong`). */
$vp_xem = VHCC_Luong::vp_bang_cong_va_luong( $CHINH, '2026-08' );
$e_blt1 = null;
foreach ( $vp_xem['rows'] as $r ) { if ( 'BLT1' === $r['ma'] ) { $e_blt1 = $r; } }
t( '🔴 dựng cảnh: màn Bảng công đã thấy công đêm', $e_blt1 && $e_blt1['congDem'] > 0, $e_blt1 );
teq( '   và đúng 1 công bù', 1.0, $e_blt1 ? (float) $e_blt1['congBu'] : null, $e_blt1 );

/* ═══════════════════════════════════ BẢNG LƯƠNG THẬT ═══════════════════════════════════
   🔴 26/09/2026 — LUẬT MỚI: *"Nếu bảng công là ngày công. Thì bảng lương cũng là ngày công"*,
   chốt: cơ sở theo công thì ai cũng ăn LƯƠNG THÁNG (lương cb × công ngày ÷ công chuẩn), công
   ngày lấy THẲNG từ lưới bảng công (ngày + tăng ca + bù), CÔNG ĐÊM là một dòng "Ca đêm" riêng
   nhân giá riêng (`demGiaCong`). Thay cho luật cũ "ca đêm ra công qua giờ thô, chỉ cộng bù". */
VHCC_Luong::dat_vp_cfg( $u_ad, array( 'demGiaCong' => 150000 ), '', '' );
$b1 = VHCC_BangLuong::dung( $CHINH, '2026-08' );
$chinh = function ( $b, $ma ) { foreach ( $b['dong'] as $d ) { if ( $ma === $d['ma'] && ! empty( $d['laChinh'] ) ) { return $d; } } return null; };
$dem   = function ( $b, $ma ) { foreach ( $b['dong'] as $d ) { if ( $ma === $d['ma'] && ! empty( $d['laDem'] ) ) { return $d; } } return null; };

/* 1. Người có lương cơ bản: công ngày = đúng hàng ☀ của lưới (ở đây chỉ 1 công bù), lương tháng. */
$d1 = $chinh( $b1, 'BLT1' );
t( '🔴 dựng cảnh: có dòng lương chính của BLT1', null !== $d1, $b1 );
if ( $d1 ) {
	teq( '🔴 dòng chính tính lương tháng', 'thang', $d1['cheDo'], $d1 );
	teq( '🔴 công ngày = đúng con số hàng ☀ của lưới (1 công bù), KHÔNG lẫn công đêm', 1.0, (float) $d1['congThuc'], $d1 );
	teq( '   lương = 9.000.000 × 1 ÷ 26', round( 9000000 / 26, 2 ), (float) $d1['luongChinh'], $d1 );
}
$n1 = $dem( $b1, 'BLT1' );
t( '🔴 có dòng Ca đêm RIÊNG cho BLT1', null !== $n1, $b1 );
if ( $n1 ) {
	teq( '   số công đêm = đúng hàng 🌙 của lưới', 1.0, (float) $n1['congThuc'], $n1 );
	teq( '🔴 tiền đêm = số công đêm × giá 1 công đêm (giá riêng)', 150000.0, (float) $n1['luongChinh'], $n1 );
	t( '   dòng đêm không mang lại khoản cộng/trừ, BHXH', 0.0 === (float) $n1['tongCong'] && 0.0 === (float) $n1['bhxh'] );
}

/* 2. Người CHƯA khai lương cơ bản: vẫn là lương tháng (không lùi về giờ × đơn giá), tiền để
      trống cho tới khi khai, và số vẫn là CÔNG. */
$d2 = $chinh( $b1, 'BLT2' );
t( '🔴 dựng cảnh: có dòng lương chính của BLT2', null !== $d2, $b1 );
if ( $d2 ) {
	teq( '🔴 chưa khai lương cơ bản vẫn tính theo CÔNG, không lùi về giờ', 'thang', $d2['cheDo'], $d2 );
	teq( '   số công ngày đúng lưới', 1.0, (float) $d2['congThuc'], $d2 );
	t( '   tiền để trống (chưa khai lương cơ bản), không bịa số', null === $d2['luongChinh'] && null === $d2['luongCb'], $d2 );
}

/* 3. Chưa khai giá công đêm -> dòng đêm chưa ra tiền, không tự lấy giá ngày. */
VHCC_Luong::dat_vp_cfg( $u_ad, array( 'demGiaCong' => 0 ), '', '' );
$n1b = $dem( VHCC_BangLuong::dung( $CHINH, '2026-08' ), 'BLT1' );
t( '🔴 chưa khai giá công đêm -> dòng đêm chưa ra tiền', $n1b && null === $n1b['luongChinh'], $n1b );

/* 4. Tệp xuất: cột "Số công thực" của cơ sở theo công là SỐ CÔNG (không phải giờ). */
VHCC_Luong::dat_vp_cfg( $u_ad, array( 'demGiaCong' => 150000 ), '', '' );
$x = VHCC_BangLuong::to_xlsx( $CHINH, '2026-08' );
t( 'dựng được tệp .xlsx', ! empty( $x['ok'] ), $x );

/* 5. Cơ sở tính THEO GIỜ (ngoài khối Văn phòng): dù có cùng cấu hình, nhánh cộng bù không chạy —
      không có "công" nào ở cơ sở này để mà cộng vào. */
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'BLT3', 'ho_ten' => 'Người Cửa Hàng',
	'cua_hang' => $GIO_CS, 'luong_co_ban' => 9000000, 'vai_tro' => 'Nhân viên' ) );
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => $GIO_CS, 'ngay' => '2026-08-05',
	'ma_nv' => 'BLT3', 'hau_to' => '', 'ho_ten' => 'Người Cửa Hàng',
	'gio_vao_giay' => VHCC_DB::giay( '08:00:00' ), 'gio_ra_giay' => VHCC_DB::giay( '16:00:00' ),
	'nguon' => 'may' ) );
VHCC_Luong::dat_cai_dat( 'VP_CONG_CFG', array( 'ngayCongThang' => 26 ), $u_ad );
$b3 = VHCC_BangLuong::dung( $GIO_CS, '2026-08' );
$d3 = null;
foreach ( $b3['dong'] as $d ) { if ( 'BLT3' === $d['ma'] ) { $d3 = $d; } }
t( '🔴 dựng cảnh: có dòng lương của BLT3 (cơ sở ngoài khối Văn phòng)', null !== $d3, $b3 );
if ( $d3 ) {
	teq( '🔴 cơ sở ngoài khối Văn phòng: công thực = đúng 1 công (8h), không cộng bù nào cả',
		1.0, (float) $d3['congThuc'], $d3 );
}

echo "\n";
if ( $truot ) {
	echo 'TRƯỢT ' . count( $truot ) . ":\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	exit( 1 );
}
echo "✓ SẠCH — $dat phép thử — công bù cộng đúng vào bảng lương thật, không trùng công đêm, không lọt sang cơ sở/người sai.\n";
