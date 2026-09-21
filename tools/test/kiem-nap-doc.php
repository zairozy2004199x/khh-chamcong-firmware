<?php
/**
 * KIỂM BỘ ĐỌC BẢNG CÔNG CŨ — khuôn "mỗi ngày một dòng, mỗi người ba cột".
 *
 * ═════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 BÀI NÀY CANH TIỀN, KHÔNG CANH CÚ PHÁP.
 * ═════════════════════════════════════════════════════════════════════════════════════════════
 * Một bộ đọc bảng công sai thì không sập, không báo lỗi — nó chỉ cho ra những con số hợp lệ mà
 * sai. Và mỗi con số ở đây là tiền lương của một người. Bốn chỗ hỏng, xếp theo mức đắt:
 *
 *   1. HAI CA CÁCH QUÃNG NỐI THẲNG HAI ĐẦU. Ngay trong tệp anh Thắng gửi, ngày 11 của N.Kiệt là
 *      08:30→13:00 và 17:05→22:00. Nối lại thành 08:30→22:00 là 13 giờ 30, trong khi thực là
 *      9 giờ 25. Dư BỐN TIẾNG cho một người trong một ngày.
 *   2. Ô `0` ĐỌC THÀNH NỬA ĐÊM. Bảng điền `0` vào mọi ô không có ca; đọc thành 00:00:00 là mỗi
 *      người có một lượt chấm lúc nửa đêm ở MỌI ngày nghỉ.
 *   3. CỤM CỘT DÒ BẰNG PHÉP CỘNG CỐ ĐỊNH. Chèn một cột ghi chú giữa bảng là lệch hết, và lấy
 *      nhầm ô thì cả tháng sai giờ mà không có gì kêu.
 *   4. `(LT)` KHÔNG GOM VỀ CÙNG NGƯỜI. Hai cụm thành hai người, và mỗi người mất một nửa công.
 *
 * Chạy: php tools/test/kiem-nap-doc.php
 */

require_once __DIR__ . '/wp-stub.php';
$goc = dirname( dirname( __DIR__ ) );
vhcc_test_boot( $goc . '/wordpress/vhcp-cham-cong' );

$dat = 0; $truot = array();
function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( null === $them ? '' : ' — ' . ( is_scalar( $them ) ? $them : wp_json_encode( $them ) ) );
}
function hhmm( $g ) { return ( null === $g ) ? 'null' : VHCC_DB::hhmm( $g ); }

/* ─────────────────────────────────────────────────────────────────────────────────────────
   BẢNG MẪU — dựng theo ĐÚNG ảnh anh Thắng gửi 21/09/2026, kể cả mấy chỗ khó:
     · tên người chỉ ghi ở cột đầu cụm (ô gộp), hai ô sau rỗng;
     · ô không có ca điền `0`;
     · cụm `(LT)` và cụm thường của cùng một người;
     · ngày 11 của N.Kiệt là hai ca CÁCH QUÃNG — chính là ca đắt nhất của bài này;
     · cuối bảng có dòng Lễ · Biên Bản · Tổng, không phải ngày.
   ───────────────────────────────────────────────────────────────────────────────────────── */
$B = array(
	array( '', '', '', 'BẢNG CHẤM CÔNG THÁNG 4/2026' ),
	array( 'Thời gian/Ngày', 'Ngân', '', '', 'K.Oanh (LT)', '', '', 'K.Oanh', '', '',
		'N.Kiệt (LT)', '', '', 'N.Kiệt', '', '' ),
	array( '', 'Check in', 'Check out', 'Số giờ làm', 'Check in', 'Check out', 'Số giờ làm',
		'Check in', 'Check out', 'Số giờ làm', 'Check in', 'Check out', 'Số giờ làm',
		'Check in', 'Check out', 'Số giờ làm' ),
	/* ngày 1: chỉ Ngân nghỉ, K.Oanh (LT) có ca */
	array( '1', '0', '0', '00:00', '09:35', '13:10', '03:35', '0', '0', '00:00',
		'0', '0', '00:00', '0', '0', '00:00' ),
	/* ngày 3: K.Oanh hai ca LIỀN NHAU 09:35→13:00 rồi 13:00→17:00 */
	array( '3', '0', '0', '00:00', '09:35', '13:00', '03:25', '13:00', '17:00', '04:00',
		'0', '0', '00:00', '0', '0', '00:00' ),
	/* 🔴 ngày 11: N.Kiệt hai ca CÁCH QUÃNG 08:30→13:00 và 17:05→22:00 */
	array( '11', '08:20', '19:10', '10:50', '0', '0', '00:00', '0', '0', '00:00',
		'17:05', '22:00', '04:55', '08:30', '13:00', '04:30' ),
	/* ngày 31: tháng 4 KHÔNG có ngày 31 */
	array( '31', '0', '0', '00:00', '0', '0', '00:00', '0', '0', '00:00',
		'0', '0', '00:00', '0', '0', '00:00' ),
	array( 'Lễ' ),
	array( 'Biên Bản' ),
	array( 'Tổng', '', '', '101:35' ),
);

$r = VHCC_NapDoc::doc( $B );
t( 'đọc được bảng', ! empty( $r['ok'] ), $r['canh'] );
t( '🔴 lấy đúng tháng từ tiêu đề', '2026-04' === $r['thang'], $r['thang'] );

/* ⚠️ `(LT)` phải gom về CÙNG một tên. Không gom thì mỗi người mất một nửa công. */
t( '🔴 hai cụm (LT) và thường gom về MỘT tên', in_array( 'K.Oanh', $r['nguoi'], true )
	&& ! in_array( 'K.Oanh (LT)', $r['nguoi'], true ), $r['nguoi'] );
t( '   và đúng ba người', array( 'K.Oanh', 'N.Kiệt', 'Ngân' ) === $r['nguoi'], $r['nguoi'] );

$tim = function ( $ten, $ngay ) use ( $r ) {
	foreach ( $r['luot'] as $x ) { if ( $x['ten'] === $ten && $x['ngay'] === $ngay ) { return $x; } }
	return null;
};

/* ══════════════════════════════ HAI CA LIỀN NHAU -> MỘT KHUNG, KHÔNG CÓ NGHỈ */

$x = $tim( 'K.Oanh', '2026-04-03' );
t( 'K.Oanh ngày 3 có lượt', null !== $x, $r['luot'] );
t( '   vào 09:35', $x && '09:35' === hhmm( $x['vao'] ), $x ? hhmm( $x['vao'] ) : '' );
t( '   ra 17:00', $x && '17:00' === hhmm( $x['ra'] ), $x ? hhmm( $x['ra'] ) : '' );
/* Hai ca liền nhau thì KHÔNG có khoảng nghỉ — ghi một khoảng rỗng 13:00–13:00 là rác. */
t( '🔴 hai ca LIỀN NHAU -> không ghi khoảng nghỉ', $x && null === $x['nghiTu'], $x );
t( '   tổng đúng 7h25 như bảng gốc (3:25 + 4:00)',
	$x && ( $x['ra'] - $x['vao'] ) === ( 7 * 3600 + 25 * 60 ), $x );

/* ══════════════════════════ 🔴 HAI CA CÁCH QUÃNG -> PHẢI TRỪ KHOẢNG GIỮA */

$x = $tim( 'N.Kiệt', '2026-04-11' );
t( 'N.Kiệt ngày 11 có lượt', null !== $x, $r['luot'] );
t( '   vào 08:30 (ca sớm nhất)', $x && '08:30' === hhmm( $x['vao'] ), $x ? hhmm( $x['vao'] ) : '' );
t( '   ra 22:00 (ca muộn nhất)', $x && '22:00' === hhmm( $x['ra'] ), $x ? hhmm( $x['ra'] ) : '' );
/* 🔴 PHÉP CHÍNH CỦA CẢ BÀI. Không có hai dòng này thì ngày ấy trả dư bốn tiếng. */
t( '🔴 ghi khoảng nghỉ TỪ 13:00', $x && '13:00' === hhmm( $x['nghiTu'] ), $x ? hhmm( $x['nghiTu'] ) : '' );
t( '🔴 ghi khoảng nghỉ ĐẾN 17:05', $x && '17:05' === hhmm( $x['nghiDen'] ), $x ? hhmm( $x['nghiDen'] ) : '' );
$thuc = $x ? ( $x['ra'] - $x['vao'] - ( $x['nghiDen'] - $x['nghiTu'] ) ) : 0;
t( '🔴 giờ thực 9h25 đúng như bảng gốc (4:30 + 4:55), KHÔNG phải 13h30',
	$thuc === ( 9 * 3600 + 25 * 60 ), gmdate( 'H:i', $thuc ) );

/* Ngân ngày 11 một ca — không dính gì tới chuyện trên. */
$x = $tim( 'Ngân', '2026-04-11' );
t( 'Ngân ngày 11 một ca, không có khoảng nghỉ',
	$x && '08:20' === hhmm( $x['vao'] ) && '19:10' === hhmm( $x['ra'] ) && null === $x['nghiTu'], $x );

/* ═══════════════════════════════════ Ô `0` KHÔNG PHẢI NỬA ĐÊM */

/* 🔴 Bảng điền `0` vào mọi ô không có ca. Đọc thành 00:00:00 là mỗi người có một lượt chấm lúc
   nửa đêm ở MỌI ngày nghỉ — bảng công đầy ngày 0 giờ mà nhìn thì tưởng có đi làm. */
t( '🔴 ô `0` KHÔNG thành lượt chấm', null === $tim( 'Ngân', '2026-04-01' ), $r['luot'] );

/* ⚠️ VÀ PHẢI THỬ CẢ DẠNG `00:00`. Phá thử bản đầu cho thấy bỏ hẳn phép chặn mà bài vẫn XANH:
   chuỗi `0` trơn không khớp khuôn `giờ:phút` nên nó rơi về null hộ. Đúng cái ô nguy hiểm —
   ô giờ trong Excel hiện ra `00:00` — thì không phép nào chạm tới. Một phép thử xanh nhờ lý do
   khác là một phép thử không canh gì cả. */
$B0 = array(
	array( 'BẢNG CHẤM CÔNG THÁNG 6/2026' ),
	array( 'Ngày', 'A', '', '' ),
	array( '', 'Check in', 'Check out', 'Số giờ làm' ),
	array( '4', '00:00', '00:00', '00:00' ),
	array( '5', '00:00:00', '00:00:00', '00:00' ),
	array( '6', '0.0', '0.0', '00:00' ),
	array( '7', '08:00', '17:00', '09:00' ),
);
$r0 = VHCC_NapDoc::doc( $B0 );
t( '🔴 ô `00:00` KHÔNG thành lượt chấm lúc nửa đêm', 1 === count( $r0['luot'] ), $r0['luot'] );
t( '   và ngày CÓ ca thật thì vẫn nạp',
	1 === count( $r0['luot'] ) && '2026-06-07' === $r0['luot'][0]['ngay'], $r0['luot'] );
t( '🔴 ngày cả bảng đều `0` thì không sinh lượt nào',
	null === $tim( 'K.Oanh', '2026-04-11' ), $r['luot'] );

/* ═══════════════════════════════════ NGÀY KHÔNG CÓ THẬT */

t( '🔴 tháng 4 không có ngày 31 -> bỏ', null === $tim( 'Ngân', '2026-04-31' ) );
/* Và dòng Lễ · Biên Bản · Tổng không được thành ngày. */
foreach ( $r['luot'] as $x ) {
	t( 'không có lượt nào ngày rỗng', '' !== $x['ngay'] );
}

/* ═════════════════════════ CỤM CỘT DÒ THEO "Check in", KHÔNG CỘNG CỐ ĐỊNH */

/* 🔴 Chèn một cột ghi chú giữa bảng: phép cộng +3 lệch hết, còn dò theo "Check in" thì không. */
$B2 = $B;
foreach ( $B2 as $i => $d ) {
	$d2 = array_slice( $d, 0, 4 );
	$d2[] = ( 1 === $i ) ? '' : ( ( 2 === $i ) ? 'Ghi chú' : 'x' );
	foreach ( array_slice( $d, 4 ) as $o ) { $d2[] = $o; }
	$B2[ $i ] = $d2;
}
$r2 = VHCC_NapDoc::doc( $B2 );
$x2 = null;
foreach ( $r2['luot'] as $z ) { if ( 'N.Kiệt' === $z['ten'] && '2026-04-11' === $z['ngay'] ) { $x2 = $z; } }
t( '🔴 chèn thêm một cột giữa bảng -> VẪN đọc đúng',
	$x2 && '08:30' === hhmm( $x2['vao'] ) && '22:00' === hhmm( $x2['ra'] ), $x2 );

/* ═══════════════════════════════════ BA CA THÌ CHỐI, KHÔNG ĐOÁN */

/* 🔴 Một hàng chỉ giữ được MỘT khoảng nghỉ. Ghi bừa một khoảng là giấu mất khoảng khác, và
   giấu theo hướng TRẢ DƯ. */
$B3 = array(
	array( 'BẢNG CHẤM CÔNG THÁNG 5/2026' ),
	array( 'Ngày', 'A (LT)', '', '', 'A', '', '', 'A (TC)', '', '' ),
	array( '', 'Check in', 'Check out', 'Số giờ làm', 'Check in', 'Check out', 'Số giờ làm',
		'Check in', 'Check out', 'Số giờ làm' ),
	array( '2', '07:00', '09:00', '02:00', '12:00', '14:00', '02:00', '18:00', '20:00', '02:00' ),
);
$r3 = VHCC_NapDoc::doc( $B3 );
t( '🔴 ba ca một ngày -> KHÔNG nạp ngày ấy', 0 === count( $r3['luot'] ), $r3['luot'] );
$co_canh = false;
foreach ( $r3['canh'] as $c ) { if ( false !== mb_strpos( $c, '3 ca' ) ) { $co_canh = true; } }
t( '   và nói rõ vì sao, kèm tên với ngày', $co_canh, $r3['canh'] );

/* ═══════════════════════════════════ TÊN GỐC */

t( 'bỏ ngoặc ở cuối', 'K.Oanh' === VHCC_NapDoc::ten_goc( 'K.Oanh (LT)' ) );
t( 'không có ngoặc thì giữ nguyên', 'Ngân' === VHCC_NapDoc::ten_goc( 'Ngân' ) );
/* ⚠️ CHỈ bỏ ngoặc Ở CUỐI. Bỏ mọi ngoặc thì "Nguyễn (Bé) Hai" cụt mất phần giữa, và hai người
   khác nhau gom nhầm về một. */
t( '🔴 ngoặc ở GIỮA tên thì giữ nguyên',
	'Nguyễn (Bé) Hai' === VHCC_NapDoc::ten_goc( 'Nguyễn (Bé) Hai' ),
	VHCC_NapDoc::ten_goc( 'Nguyễn (Bé) Hai' ) );

/* ═══════════════════════════════════ TỆP KHÔNG ĐÚNG KHUÔN */

$r4 = VHCC_NapDoc::doc( array( array( 'Họ và Tên', 'ID', '2026-07-01' ) ) );
t( 'tệp khác khuôn -> chối, không đoán', empty( $r4['ok'] ), $r4 );
t( '   và nói rõ thiếu gì', ! empty( $r4['canh'] ), $r4 );

/* ═════════════════════════════════════════════════════════════════════════════════════════
   ĐƯỜNG GHI — TỪ BẢNG VÀO SỔ CHẤM CÔNG
   ═════════════════════════════════════════════════════════════════════════════════════════
   🔴 PHẦN NÀY CANH ĐÚNG MỘT CON SỐ: 9 GIỜ 25 CỦA N.KIỆT NGÀY 11.
      Bộ đọc trả về khoảng nghỉ đúng chưa đủ — khoảng ấy phải ĐẾN ĐƯỢC cột `nghi_tu_giay` của
      hàng trong sổ, và phép tính công của chính hệ (`VHCC_PDF::phut_lam`, thứ in ra tờ A4 và
      chạy trong bảng lương) phải nhìn thấy nó. Đứt ở bất kỳ mắt nào giữa hai đầu thì ngày ấy
      thành 13 giờ 30, và không có gì kêu.
   ───────────────────────────────────────────────────────────────────────────────────────── */

global $wpdb;
$CS = 'NAPDOC_CS';
$nv = function ( $ma, $ten, $vai = 'Nhân viên' ) use ( $wpdb, $CS ) {
	$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array(
		'ma_nv' => $ma, 'ho_ten' => $ten, 'cua_hang' => $CS, 'vai_tro' => $vai,
		'pin_dang_nhap' => '', 'sdt' => '0900000000',
		'chuc_vu' => 'Nhân viên quầy', 'trang_thai_lam_viec' => 'Đang làm' ) );
};
$nv( 'ND1', 'Trần Thị Ngân' );
$nv( 'ND2', 'Lê Thị Kim Oanh' );
$nv( 'ND3', 'Nguyễn Tuấn Kiệt' );
/* ⚠️ NGƯỜI THỨ HAI CÙNG TÊN GỌI — cố ý. Một cửa hàng có hai cô "Ngân" là chuyện thường, và
   đó chính là chỗ mà một phép đoán tự tin sẽ rót công của người này sang người kia. */
$nv( 'ND4', 'Phạm Thị Ngân' );
$nv( 'ND9', 'Sếp Tổng', 'Admin' );
$AD = array( 'ma_nv' => 'ND9', 'name' => 'Sếp Tổng', 'vai_tro' => 'Admin', 'cua_hang' => $CS );
$NV = array( 'ma_nv' => 'ND1', 'name' => 'Trần Thị Ngân', 'vai_tro' => 'Nhân viên', 'cua_hang' => $CS );

$ds_nv = VHCC_NhanSu::ds_nhan_vien( $AD, $CS );
t( 'thấy đủ năm hồ sơ của cơ sở', 5 === count( $ds_nv ), count( $ds_nv ) );

/* ══════════════════════════════════════════════════════════════════ GỢI Ý GHÉP TÊN */

$g = VHCC_NapDoc::goi_y( 'N.Kiệt', $ds_nv );
t( 'gợi ý "N.Kiệt" ra đúng một người', array( 'ND3' ) === $g, $g );
$g = VHCC_NapDoc::goi_y( 'K.Oanh', $ds_nv );
t( 'gợi ý "K.Oanh" ra đúng một người', array( 'ND2' ) === $g, $g );
/* 🔴 HAI NGƯỜI CÙNG TÊN GỌI -> TRẢ CẢ HAI, ĐỂ MÀN BỎ TRỐNG Ô CHỌN.
   Chọn đại người đầu danh sách là cả tháng công của cô này chui vào bảng lương cô kia — sai
   im lặng, và sai ra tiền. */
$g = VHCC_NapDoc::goi_y( 'Ngân', $ds_nv );
sort( $g );
t( '🔴 "Ngân" khớp HAI người -> trả cả hai, không tự chọn', array( 'ND1', 'ND4' ) === $g, $g );
/* Chữ viết tắt phải khớp một tiếng đứng TRƯỚC tiếng cuối. "T.Kiệt" khớp "Nguyễn **T**uấn Kiệt". */
t( 'viết tắt khớp tiếng giữa', array( 'ND3' ) === VHCC_NapDoc::goi_y( 'T.Kiệt', $ds_nv ) );
/* ⚠️ Viết tắt KHÔNG khớp thì thôi, đừng nới ra cho "gần đúng": "X.Kiệt" mà vẫn ra ND3 nghĩa là
   phần viết tắt bị bỏ qua, và mọi cái tên cùng tiếng cuối sẽ khớp bừa. */
t( '🔴 viết tắt sai -> KHÔNG khớp', array() === VHCC_NapDoc::goi_y( 'X.Kiệt', $ds_nv ),
	VHCC_NapDoc::goi_y( 'X.Kiệt', $ds_nv ) );
t( 'tên lạ hẳn -> không gợi ý gì', array() === VHCC_NapDoc::goi_y( 'Bảy', $ds_nv ) );
/* Tên trong bảng CHÍNH LÀ mã NV thì khớp thẳng. */
t( 'ô ghi thẳng mã NV -> khớp ngay', array( 'ND3' ) === VHCC_NapDoc::goi_y( 'ND3', $ds_nv ) );

/* ══════════════════════════════════════════════════════════════════════ QUYỀN */

$r_nv = VHCC_NapDoc::nap( $NV, $CS, $B, array(), true );
t( '🔴 nhân viên thường KHÔNG nạp được', empty( $r_nv['ok'] ), $r_nv );
/* ⚠️ SOI CẢ CÂU BÁO, không chỉ soi `ok`. Người này vướng HAI chốt cùng lúc (bậc quyền, và
   phạm vi cơ sở), nên chỉ hỏi `ok` thì gỡ hẳn chốt bậc quyền mà bài vẫn XANH — chốt kia đỡ
   hộ. Phá thử đã cho thấy đúng thế. Câu báo là thứ duy nhất phân biệt được hai chốt. */
t( '   và chối vì BẬC QUYỀN, không phải vì cơ sở',
	isset( $r_nv['error'] ) && false !== strpos( $r_nv['error'], 'Quản lý' ), $r_nv );
$r_ks = VHCC_NapDoc::nap( $AD, '', $B, array(), true );
t( 'chưa chọn cơ sở -> chối', empty( $r_ks['ok'] ), $r_ks );

/* ═════════════════════════════════════════════════════ XEM TRƯỚC KHÔNG GHI GÌ */

$dem = function () use ( $wpdb, $CS ) {
	return (int) $wpdb->get_var( $wpdb->prepare(
		'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'cham_cong' ) . ' WHERE coso=%s', $CS ) );
};
$r1 = VHCC_NapDoc::nap( $AD, $CS, $B, array(), true );
t( 'xem trước chạy được', ! empty( $r1['ok'] ), $r1 );
t( '   chưa ghép tên nào -> còn thiếu ba', 3 === (int) $r1['so_thieu'], $r1['so_thieu'] );
t( '🔴 xem trước KHÔNG ghi một dòng nào', 0 === $dem(), $dem() );
t( '   và nói rõ số lượt bị bỏ', (int) $r1['bo_luot'] === (int) $r1['so_luot'], $r1 );

/* ═══════════════════════════════════════════════ NẠP THẬT SAU KHI GHÉP TÊN */

$map = array( 'Ngân' => 'ND1', 'K.Oanh' => 'ND2', 'N.Kiệt' => 'ND3' );
$r2  = VHCC_NapDoc::nap( $AD, $CS, $B, $map, false );
t( 'nạp thật chạy được', ! empty( $r2['ok'] ), $r2 );
t( '   không còn tên nào chưa ghép', 0 === (int) $r2['so_thieu'], $r2 );
t( '   ghi đủ số ngày công đọc được', (int) $r2['da_ghi'] === (int) $r2['so_luot'], $r2 );

$hang = function ( $ma, $ngay ) use ( $wpdb, $CS ) {
	return $wpdb->get_row( $wpdb->prepare(
		'SELECT * FROM ' . VHCC_DB::t( 'cham_cong' ) . ' WHERE coso=%s AND ma_nv=%s AND ngay=%s',
		$CS, $ma, $ngay ), ARRAY_A );
};

$h = $hang( 'ND2', '2026-04-03' );
t( 'K.Oanh ngày 3 vào sổ', $h, $h );
t( '   vào 09:35', $h && '09:35' === hhmm( (int) $h['gio_vao_giay'] ) );
t( '   ra 17:00', $h && '17:00' === hhmm( (int) $h['gio_ra_giay'] ) );
/* Hai ca LIỀN NHAU -> không có khoảng nghỉ. Ghi một khoảng 13:00–13:00 là rác, và mọi phép trừ
   về sau phải tự đoán xem nó có nghĩa gì. */
t( '🔴 hai ca liền nhau -> ô nghỉ để TRỐNG',
	$h && ( null === $h['nghi_tu_giay'] || '' === $h['nghi_tu_giay'] ), $h );

/* ═══════════════════════════════════════════════════════════════════════════════════════
   🔴 CON SỐ ĐẮT NHẤT CỦA CẢ BÀI
   ═══════════════════════════════════════════════════════════════════════════════════════ */
$k = $hang( 'ND3', '2026-04-11' );
t( 'N.Kiệt ngày 11 vào sổ', $k, $k );
t( '   khung ngoài 08:30 → 22:00', $k && '08:30' === hhmm( (int) $k['gio_vao_giay'] )
	&& '22:00' === hhmm( (int) $k['gio_ra_giay'] ), $k );
t( '🔴 khoảng nghỉ giữa hai ca ĐÃ VÀO SỔ: 13:00 → 17:05',
	$k && '13:00' === hhmm( (int) $k['nghi_tu_giay'] ) && '17:05' === hhmm( (int) $k['nghi_den_giay'] ), $k );
/* Phép tính công của CHÍNH HỆ — thứ in ra tờ A4 và chạy trong bảng lương. Đây mới là chỗ con
   số thành tiền; khoảng nghỉ nằm đúng cột mà phép tính không nhìn thấy thì vẫn trả dư như cũ. */
$phut = $k ? VHCC_PDF::phut_lam( (int) $k['gio_vao_giay'], (int) $k['gio_ra_giay'],
	$k['nghi_tu_giay'], $k['nghi_den_giay'] ) : null;
t( '🔴 hệ tính ra 9 GIỜ 25, KHÔNG PHẢI 13 GIỜ 30', 565 === (int) $phut,
	( null === $phut ? 'null' : ( intdiv( $phut, 60 ) . 'h' . ( $phut % 60 ) ) ) );

/* Ngày 31 của tháng 4 không có thật -> không được đẻ ra hàng nào. */
t( '🔴 ngày 31 của tháng 4 không vào sổ', null === $hang( 'ND3', '2026-04-31' ) );

/* ═══════════════════════════════════════════════════════ NẠP LẠI KHÔNG SINH TRÙNG */

$truoc = $dem();
$r3 = VHCC_NapDoc::nap( $AD, $CS, $B, array(), false );
t( '🔴 lần nạp thứ hai KHÔNG cần ghép lại tên (sổ ghép đã nhớ)', 0 === (int) $r3['so_thieu'], $r3 );
t( '🔴 nạp lại không sinh thêm hàng nào', $truoc === $dem(), $truoc . ' -> ' . $dem() );
$k2 = $hang( 'ND3', '2026-04-11' );
t( '   và giờ vẫn y nguyên', $k2 && (int) $k2['gio_vao_giay'] === (int) $k['gio_vao_giay']
	&& (int) $k2['gio_ra_giay'] === (int) $k['gio_ra_giay'], $k2 );
t( '   khoảng nghỉ cũng y nguyên', $k2 && (int) $k2['nghi_tu_giay'] === (int) $k['nghi_tu_giay'], $k2 );

/* ═══════════════════════════════════════════════════════════════════ SỔ GHÉP TÊN */

$so = VHCC_NapDoc::so_ghep( $CS );
t( 'sổ ghép nhớ đủ ba tên', 'ND1' === $so['Ngân'] && 'ND2' === $so['K.Oanh'] && 'ND3' === $so['N.Kiệt'], $so );
/* 🔴 NHỚ THEO CƠ SỞ. Hai cửa hàng đều có một cô "Ngân"; một sổ chung là rót nhầm người. */
t( '🔴 sổ của cơ sở khác thì rỗng', array() === VHCC_NapDoc::so_ghep( 'NAPDOC_CS_KHAC' ),
	VHCC_NapDoc::so_ghep( 'NAPDOC_CS_KHAC' ) );
/* Chọn lại ô trống = GỠ phép ghép. Không gỡ được thì một phép ghép sai nằm lại vĩnh viễn. */
VHCC_NapDoc::luu_ghep( $AD, $CS, array( 'Ngân' => '' ) );
$so = VHCC_NapDoc::so_ghep( $CS );
t( 'chọn ô trống thì GỠ được phép ghép', ! isset( $so['Ngân'] ), $so );
VHCC_NapDoc::luu_ghep( $AD, $CS, array( 'Ngân' => 'ND1' ) );

/* Ô lạ gửi lên (tên không có trong tệp) không được chui vào sổ. */
VHCC_NapDoc::nap( $AD, $CS, $B, array( 'Ai Đó Lạ' => 'ND4' ), true );
t( '🔴 tên không có trong tệp thì không vào sổ ghép',
	! isset( VHCC_NapDoc::so_ghep( $CS )['Ai Đó Lạ'] ), VHCC_NapDoc::so_ghep( $CS ) );

/* Mã đã ghép mà hồ sơ bị xoá -> coi như chưa ghép, và phải KỂ RA. */
$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . VHCC_DB::t( 'nhan_vien' ) . ' WHERE ma_nv=%s', 'ND2' ) );
$r5 = VHCC_NapDoc::nap( $AD, $CS, $B, array(), true );
t( '🔴 mã đã ghép mất hồ sơ -> coi như chưa ghép', 1 === (int) $r5['so_thieu'], $r5['so_thieu'] );
t( '   và nói ra chỗ hỏng', (bool) preg_grep( '/K\.Oanh/', (array) $r5['canh'] ), $r5['canh'] );

/* ══════════════════════════════════ KHOẢNG NGHỈ PHẢI NẰM TRONG KHUNG GIỜ CỦA HÀNG */

/* 🔴 Bộ nạp tính khoảng nghỉ từ TỆP, nhưng giờ trong sổ có thể đã bị sửa tay. Đặt bừa khoảng
   của tệp vào một khung khác là TRỪ mất mấy tiếng công — trừ im lặng, vì bảng vẫn đủ cặp giờ
   và ô nghỉ thì không ai soi. */
/* ⚠️ PHẢI THỬ TRÊN MỘT HÀNG CÓ THẬT. Bản đầu của bài này thử trên ngày 3 của Ngân — ngày ấy
   cô không có ca, nên hàm trả `false` ngay ở chốt "không có hàng" và KHÔNG bao giờ chạy tới
   chốt khung giờ. Phá thử cho thấy gỡ hẳn chốt khung mà bài vẫn XANH. Ngày 11 thì Ngân có
   hàng thật (08:20 → 19:10), và chỉ ở đó phép thử mới nói được điều nó định nói. */
$n1 = $hang( 'ND1', '2026-04-11' );
t( 'Ngân ngày 11 có hàng thật để thử', $n1 && '08:20' === hhmm( (int) $n1['gio_vao_giay'] )
	&& '19:10' === hhmm( (int) $n1['gio_ra_giay'] ), $n1 );
t( '🔴 khoảng nghỉ NẰM HẲN NGOÀI khung giờ -> chối',
	false === VHCC_Nhan::dat_nghi_giua( $CS, '2026-04-11', 'ND1', 6 * 3600, 7 * 3600 ) );
t( '🔴 khoảng nghỉ THÒ MỘT ĐẦU ra ngoài khung -> cũng chối',
	false === VHCC_Nhan::dat_nghi_giua( $CS, '2026-04-11', 'ND1', 18 * 3600, 20 * 3600 ) );
t( '   chối rồi thì ô nghỉ vẫn để trống, không ghi nửa vời',
	( function () use ( $hang ) { $x = $hang( 'ND1', '2026-04-11' );
		return $x && ( null === $x['nghi_tu_giay'] || '' === $x['nghi_tu_giay'] ); } )() );
t( '   hàng không có thật -> chối',
	false === VHCC_Nhan::dat_nghi_giua( $CS, '2026-04-29', 'ND1', 13 * 3600, 14 * 3600 ) );
/* Hàng THIẾU một đầu giờ thì không biết khung ở đâu -> cũng chối. */
VHCC_Nhan::ghi_gio( $CS, '2026-04-28', 'ND1', 'Trần Thị Ngân', 8 * 3600, '', 'sheet' );
t( '🔴 hàng thiếu giờ ra -> chối, không đoán khung',
	false === VHCC_Nhan::dat_nghi_giua( $CS, '2026-04-28', 'ND1', 12 * 3600, 13 * 3600 ) );
/* 🔴 VÀ THIẾU GIỜ VÀO CŨNG THẾ — phép thử này KHÔNG thừa.
   Phá thử cho thấy gỡ hẳn hai nhánh `null === $v || null === $r` mà bài vẫn XANH: hàng thiếu
   giờ RA lọt lưới nhờ một tai nạn của PHP (`$den > null` ép null thành 0 nên vẫn ra true).
   Nhưng hàng thiếu giờ VÀO thì tai nạn ấy đỡ ngược: `$tu < null` thành `$tu < 0` = false, và
   khoảng nghỉ chui thẳng vào một hàng không có khung. Chỉ phép thử này bắt được. */
$wpdb->update( VHCC_DB::t( 'cham_cong' ),
	array( 'gio_vao_giay' => null, 'gio_ra_giay' => 19 * 3600 ),
	array( 'coso' => $CS, 'ngay' => '2026-04-28', 'ma_nv' => 'ND1' ) );
t( '🔴 hàng thiếu giờ VÀO -> cũng chối',
	false === VHCC_Nhan::dat_nghi_giua( $CS, '2026-04-28', 'ND1', 12 * 3600, 13 * 3600 ) );
t( '   khoảng nghỉ NẰM TRONG khung thì nhận',
	true === VHCC_Nhan::dat_nghi_giua( $CS, '2026-04-11', 'ND1', 12 * 3600, 13 * 3600 ), $n1 );
$n2 = $hang( 'ND1', '2026-04-11' );
t( '   và ghi đúng vào sổ', $n2 && '12:00' === hhmm( (int) $n2['nghi_tu_giay'] )
	&& '13:00' === hhmm( (int) $n2['nghi_den_giay'] ), $n2 );

/* ══════════════════════════════════════════════════════════ TỆP GIỮ TẠM GIỮA HAI BƯỚC */

$ma_giu = VHCC_NapDoc::giu_bang( $AD, $B );
t( 'giữ được bảng tạm', '' !== $ma_giu );
t( '   và lấy lại đúng bảng ấy', $B === VHCC_NapDoc::lay_bang( $AD, $ma_giu ) );
/* 🔴 Bảng công là dữ liệu người thật — chỉ CHÍNH NGƯỜI tải lên mới lấy lại được. */
t( '🔴 người khác KHÔNG lấy được bảng tạm của mình',
	null === VHCC_NapDoc::lay_bang( $NV, $ma_giu ) );
t( 'mã bịa -> không lấy được gì', null === VHCC_NapDoc::lay_bang( $AD, 'khongcothat' ) );

echo "\n";
if ( $truot ) {
	echo 'TRƯỢT ' . count( $truot ) . ":\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — hai ca cách quãng KHÔNG bị nối thẳng thành giờ dư.\n";
