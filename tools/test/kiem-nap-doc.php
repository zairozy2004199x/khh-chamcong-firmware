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

/* ═════════════════════════════════════════════════════════════════════════════════════════
   NHỮNG GÌ TỆP THẬT CỦA ANH THẮNG DẠY (21/09/2026)
   ═════════════════════════════════════════════════════════════════════════════════════════
   Anh gửi tệp .csv thật: 33 bảng xếp dọc, từ tháng 1/2024 tới 2026, 2227 ngày công. Bảng mẫu
   dựng từ ảnh chụp KHÔNG có ba thứ dưới đây, và cả ba đều làm mất dữ liệu trong im lặng.
   ───────────────────────────────────────────────────────────────────────────────────────── */

/* ══════════════════════ 1. NHIỀU BẢNG CHỒNG NHAU -> CHỐI, KHÔNG ĐỌC BẢNG ĐẦU */

/* 🔴 CHỖ HỎNG ĐẮT NHẤT MÀ TỆP THẬT LỘ RA.
   `tim_thang()` lấy tháng ở đầu tệp, còn `tim_dong_cot()` đi tìm "Check in" trong CẢ tệp. Mười
   bảng đầu của anh Thắng (2024) chỉ ghi SỐ GIỜ mỗi ngày, không có dòng Check in — nên dòng ấy
   mãi tới bảng tháng 11/2024 mới gặp. Bộ đọc bèn lấy tiêu đề "1/2024" ghép với dữ liệu tháng 11,
   rồi đọc tiếp suốt hai năm còn lại và đóng dấu TẤT CẢ là tháng 1/2024. Ra số, số hợp lệ, sai hết.
   Anh Thắng: *"Để anh copy 1 tháng thôi — tránh lẫn lộn dữ liệu"*. Chốt này bắt máy tuân theo
   cách ấy, thay vì trông vào trí nhớ người dùng. */
$hai = array_merge( $B, array( array( '' ) ), $B );
$r_hai = VHCC_NapDoc::doc( $hai );
t( '🔴 hai bảng trong một tệp -> CHỐI', empty( $r_hai['ok'] ), $r_hai );
t( '   và nói rõ là chồng bảng', (bool) preg_grep( '/chồng nhau/u', (array) $r_hai['canh'] ), $r_hai['canh'] );

/* Dựng lại đúng cái bẫy của tệp thật: bảng ĐẦU không có dòng Check in, bảng SAU thì có. */
$bay = array_merge(
	array( array( 'BẢNG CHẤM CÔNG THÁNG 1/2024' ), array( '' ),
		array( '', 'Thắng', 'Bảo Ngô' ), array( '1', '30,5', '0' ), array( 'Tổng', '61,5', '56' ),
		array( '' ) ),
	$B );
$r_bay = VHCC_NapDoc::doc( $bay );
t( '🔴 bảng đầu không có Check in + bảng sau có -> vẫn CHỐI', empty( $r_bay['ok'] ), $r_bay );
/* ⚠️ Không đủ nếu chỉ hỏi `ok` rỗng — phải chắc nó chối vì CHỒNG BẢNG, chứ không phải vì một
   lý do khác tình cờ đỡ hộ. Không có câu này thì gỡ hẳn chốt chồng bảng mà bài vẫn có thể xanh. */
t( '   và chối đúng vì chồng bảng, không phải lý do khác',
	(bool) preg_grep( '/chồng nhau/u', (array) $r_bay['canh'] ), $r_bay['canh'] );
$ds_t = VHCC_NapDoc::ds_thang( $bay );
t( '   kể đúng hai tháng tìm thấy', array( '1/2024', '4/2026' ) === $ds_t, $ds_t );
t( 'một bảng thì không kêu gì', array( '4/2026' ) === VHCC_NapDoc::ds_thang( $B ) );

/* ══════════════════════ 2. NHÃN NGÀY CÓ ĐUÔI — NGÀY LỄ TẾT, NGÀY TRẢ NHIỀU TIỀN NHẤT */

/* 🔴 Bản đầu đòi ô ngày phải là SỐ TRẦN. Tệp thật có 17 dòng kiểu `1 (LỄ*2)`, `26 (27 TẾT)`,
   `29 M1`, `1 Mùng 4`, `7 28t` — dòng nào cũng CÓ GIỜ, riêng `1 (LỄ*2)` của tháng 5/2025 có 18
   ô giờ của 7 người. Đòi số trần là nuốt sạch mấy ngày ấy, im lặng, mà toàn ngày lễ tết. */
$le = $B;
$le[3][0] = '1 (LỄ*2)';        // ngày 1 — K.Oanh (LT) có ca 09:35–13:10
$le[4][0] = '3 Mùng 5';
$le[5][0] = '11 (GIAO THỪA)';
$r_le = VHCC_NapDoc::doc( $le );
t( 'nhãn ngày có đuôi -> vẫn đọc được bảng', ! empty( $r_le['ok'] ), $r_le );
$tim_le = function ( $ten, $ngay ) use ( $r_le ) {
	foreach ( $r_le['luot'] as $x ) { if ( $x['ten'] === $ten && $x['ngay'] === $ngay ) { return $x; } }
	return null;
};
t( '🔴 ngày "1 (LỄ*2)" KHÔNG bị nuốt', null !== $tim_le( 'K.Oanh', '2026-04-01' ), $r_le['luot'] );
t( '🔴 ngày "3 Mùng 5" KHÔNG bị nuốt', null !== $tim_le( 'K.Oanh', '2026-04-03' ) );
t( '🔴 ngày "11 (GIAO THỪA)" KHÔNG bị nuốt', null !== $tim_le( 'N.Kiệt', '2026-04-11' ) );
/* Và khoảng nghỉ của ngày 11 vẫn còn nguyên — đuôi nhãn không được làm hỏng phần đắt nhất. */
$x_le = $tim_le( 'N.Kiệt', '2026-04-11' );
t( '   và khoảng nghỉ 13:00–17:05 vẫn nguyên',
	$x_le && 13 * 3600 === $x_le['nghiTu'] && ( 17 * 3600 + 5 * 60 ) === $x_le['nghiDen'], $x_le );
/* ⚠️ Hệ số lễ KHÔNG nằm trong tệp — cột "Số giờ làm" của bảng gốc đã nhân đôi sẵn (17:00–22:45
   ghi thành 11:30), nên nạp theo cột ấy là trả gấp đôi. Ta nạp theo GIỜ VÀO/RA, và phải kể ra. */
t( '🔴 nói rõ ngày lễ chỉ nạp GIỜ, không nạp hệ số',
	(bool) preg_grep( '/LỄ\*2/u', (array) $r_le['canh'] ), $r_le['canh'] );

/* ⚠️ RANH GIỚI SAU SỐ. Không có `\b` thì "2024" đọc thành ngày 20, và một dòng tiêu đề lạc vào
   giữa bảng biến thành một ngày công. */
$lac = $B;
$lac[3][0] = '2024';
$r_lac = VHCC_NapDoc::doc( $lac );
t( '🔴 ô "2024" KHÔNG đọc thành ngày 20',
	null === ( function () use ( $r_lac ) {
		foreach ( $r_lac['luot'] as $x ) { if ( '2026-04-20' === $x['ngay'] ) { return $x; } }
		return null; } )(), $r_lac['luot'] );
/* Dòng tổng kết vẫn phải rơi ra ngoài — chúng không mở đầu bằng số. */
foreach ( array( 'Tổng', 'Lễ', 'Bù', 'Biên Bản' ) as $nhan ) {
	$bo = $B; $bo[3][0] = $nhan;
	$r_bo = VHCC_NapDoc::doc( $bo );
	t( 'dòng "' . $nhan . '" không thành một ngày công',
		0 === count( preg_grep( '/^2026-04-0[12]$/', array_column( $r_bo['luot'], 'ngay' ) ) ), $r_bo['luot'] );
}

/* ══════════════════════ 3. Ô GIỜ GÕ SAI KHÁC HẲN Ô TRỐNG */

/* 🔴 Tệp thật có `11:4`, `11:0`, `11:2` (thiếu một chữ số) và `27:50:00`, `35:10:00` (số giờ
   đã nhân đôi bị gõ nhầm vào cột Check in). Đọc về `null` thì ca ấy thành "thiếu giờ ra", im
   lặng y như ô trống — người ta đi bù tay, trong khi chuyện thật là BẢNG GỐC sai một ô. */
$xau = $B;
$xau[5][10] = '11:4';          // N.Kiệt (LT) ngày 11, ô giờ vào
$xau[5][14] = '27:50:00';      // N.Kiệt ngày 11, ô giờ ra
$r_xau = VHCC_NapDoc::doc( $xau );
t( '🔴 ô "11:4" được kể ra là gõ sai',
	(bool) preg_grep( '/11:4/u', (array) $r_xau['canh'] ), $r_xau['canh'] );
t( '🔴 ô "27:50:00" cũng được kể ra',
	(bool) preg_grep( '/27:50:00/u', (array) $r_xau['canh'] ), $r_xau['canh'] );
/* ⚠️ Ô `0` KHÔNG được kêu. Cả bảng điền `0` cho ô không có ca — kêu ở đó là mỗi tháng đẻ ra
   hàng nghìn dòng cảnh báo và không ai đọc dòng nào nữa. */
t( '🔴 ô "0" KHÔNG bị kêu là gõ sai',
	0 === count( preg_grep( '/gõ sai|không đọc được/u', (array) $r['canh'] ) ), $r['canh'] );

/* ══════════════════════ 4. THÁNG ĐÃ CÓ SẴN SỐ LIỆU -> ĐẾM VÀ NÓI RA */

/* 🔴 Tệp thật có HAI bảng cùng đề "BẢNG CHẤM CÔNG THÁNG 8/2026" — bảng sau là tháng khác, chỉ
   là chép tiêu đề quên sửa. Máy không có cách nào biết tiêu đề sai; nhưng đưa con số "tháng này
   đã có N ngày công của M người" ra trước mắt thì người cầm bảng nhận ra ngay. */
$r_lai = VHCC_NapDoc::nap( $AD, $CS, $B, array(), true );
t( '🔴 đếm đúng số ngày công tháng ấy đã có trong sổ',
	isset( $r_lai['co_san']['luot'] ) && $r_lai['co_san']['luot'] > 0, $r_lai['co_san'] );
t( '   và cảnh báo trộn số liệu',
	(bool) preg_grep( '/ĐÃ CÓ/u', (array) $r_lai['canh'] ), $r_lai['canh'] );
/* Cơ sở khác thì tháng ấy còn trống -> không kêu, kẻo cảnh báo mất thiêng. */
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'NDX', 'ho_ten' => 'Người Nơi Khác',
	'cua_hang' => 'NAPDOC_CS2', 'vai_tro' => 'Nhân viên', 'pin_dang_nhap' => '',
	'chuc_vu' => 'Nhân viên quầy', 'trang_thai_lam_viec' => 'Đang làm' ) );
$r_sach = VHCC_NapDoc::nap( $AD, 'NAPDOC_CS2', $B, array(), true );
t( 'cơ sở còn trống tháng ấy -> KHÔNG kêu trộn số liệu',
	0 === count( preg_grep( '/ĐÃ CÓ/u', (array) $r_sach['canh'] ) ), $r_sach['canh'] );

/* ═════════════════════════════════════════════════════════════════════════════════════════
   5. ĐỐI CHIẾU CỘT "SỐ GIỜ LÀM" CỦA BẢNG GỐC
   ═════════════════════════════════════════════════════════════════════════════════════════
   🔴 CỘT ẤY LÀ TIỀN CÔNG, KHÔNG PHẢI SỐ GIỜ — và đó là lý do không bao giờ nạp theo nó.
   Anh Thắng gửi riêng tháng 8/2026 (21/09/2026). Đối chiếu tổng từng người với dòng "Tổng" của
   chính bảng: 8/9 người khớp ĐẾN TỪNG PHÚT, một người lệch đúng 4 giờ — ô `ngày 30 T.Bình (LT)
   13:00→17:00` mà bảng ghi `8:00`. Bảng gốc sai, không phải bộ đọc.
   Trong cả tệp lớn: 2820 ô đối chiếu -> 18 ngày lễ (bảng tự nhân 2, riêng Tết nhân 3) và đúng
   hai ô gấp đôi LẺ LOI giữa ngày thường — hai ô hỏng thật.
   ───────────────────────────────────────────────────────────────────────────────────────── */

/* Bảng mẫu có cột "Số giờ làm" khớp hết -> không được kêu một tiếng nào. Không có phép thử này
   thì một chốt kêu bừa vẫn xanh, và cảnh báo mất thiêng ngay từ bảng sạch đầu tiên. */
t( '🔴 cột "Số giờ làm" khớp hết -> KHÔNG kêu gì',
	0 === count( preg_grep( '/Số giờ làm/u', (array) $r['canh'] ) ), $r['canh'] );

/* ── một ô lệch giữa ngày thường -> gọi ĐÍCH DANH (đúng ca thật của anh Thắng) */
$lech = $B;
$lech[5][15] = '08:30';          // N.Kiệt ngày 11: 08:30→13:00 = 4:30, nhưng ghi 8:30
$r_lech = VHCC_NapDoc::doc( $lech );
t( '🔴 ô "Số giờ làm" lệch -> kêu đích danh người và ngày',
	(bool) preg_grep( '/Ngày 11, N\.Kiệt.*8:30/u', (array) $r_lech['canh'] ), $r_lech['canh'] );
/* ⚠️ Kêu thì kêu, nhưng GIỜ NẠP VÀO vẫn phải theo giờ vào/ra, không theo cột ấy. */
$x_l = null;
foreach ( $r_lech['luot'] as $z ) { if ( 'N.Kiệt' === $z['ten'] && '2026-04-11' === $z['ngay'] ) { $x_l = $z; } }
t( '   nhưng vẫn nạp theo GIỜ VÀO/RA, không theo cột ấy',
	$x_l && ( 8 * 3600 + 30 * 60 ) === $x_l['vao'] && 22 * 3600 === $x_l['ra'], $x_l );

/* ── cả ngày nhân đôi -> MỘT dòng "ngày lễ", không kêu từng ô */
$le2 = $B;
$le2[4][6]  = '06:50';           // ngày 3: K.Oanh (LT) 3:25 -> ghi gấp đôi
$le2[4][9]  = '08:00';           //         K.Oanh      4:00 -> ghi gấp đôi
$r_le2 = VHCC_NapDoc::doc( $le2 );
t( '🔴 cả ngày gấp đôi -> nhận ra là NGÀY LỄ',
	(bool) preg_grep( '/Ngày 3: bảng gốc tính GẤP 2/u', (array) $r_le2['canh'] ), $r_le2['canh'] );
/* ⚠️ VÀ KHÔNG KÊU TỪNG Ô. Một tháng có Tết mà kêu từng ô là hàng chục dòng đều đặn, rồi không
   ai đọc dòng nào nữa — kể cả dòng thật. Đây là chỗ phép soi này sống hay chết. */
t( '   và KHÔNG kêu từng ô của ngày ấy',
	0 === count( preg_grep( '/Ngày 3, K\.Oanh/u', (array) $r_le2['canh'] ) ), $r_le2['canh'] );

/* ── Tết nhân BA: cột giờ vượt 24 tiếng (34:45) phải đọc được, nếu không thì nó rơi về null và
      biến mất khỏi phép đối chiếu — tức ngày Tết trông như ngày sạch. */
$le3 = $B;
$le3[4][6] = '10:15';            // 3:25 x3
$le3[4][9] = '12:00';            // 4:00 x3
$r_le3 = VHCC_NapDoc::doc( $le3 );
t( '🔴 cả ngày gấp BA (Tết) -> nhận ra đúng hệ số',
	(bool) preg_grep( '/Ngày 3: bảng gốc tính GẤP 3/u', (array) $r_le3['canh'] ), $r_le3['canh'] );

$vuot = $B;
$vuot[5][12] = '34:45';          // ô "Số giờ làm" vượt 24 giờ — N.Kiệt (LT) ngày 11
$r_vuot = VHCC_NapDoc::doc( $vuot );
t( '🔴 ô "Số giờ làm" VƯỢT 24 giờ vẫn soi được',
	(bool) preg_grep( '/34:45/u', (array) $r_vuot['canh'] ), $r_vuot['canh'] );

/* ══ Ô GẤP ĐÔI LẺ LOI GIỮA NGÀY THƯỜNG — ĐÚNG HÌNH DẠNG CỦA LỖI THẬT ══
   🔴 Đây mới là ca của `8/2026 ngày 30 T.Bình (LT) 13:00→17:00 ghi 8:00`, và là phép thử suýt
   không có. Bản đầu em dựng ô lệch bằng một con số KHÔNG chia hết (4:30 ghi 8:30) — ô ấy rơi
   thẳng vào nhánh "lệch lung tung" nên nhánh xét ngày lễ không hề chạy. Phá thử lộ ra: đổi điều
   kiện ngày lễ thành `true` (tức coi MỌI ngày có ô lệch là ngày lễ, nuốt mất đúng cái ô hỏng
   thật) mà bài vẫn XANH. Phải là ô GẤP ĐÔI CHẴN, giữa một ngày mà những ô khác đều khớp. */
$don = $B;
$don[5][12] = '09:50';           // N.Kiệt (LT) ngày 11: 4:55 thật, ghi 9:50 = gấp đôi chẵn
$r_don = VHCC_NapDoc::doc( $don );
t( '🔴 ô gấp đôi LẺ LOI giữa ngày thường -> gọi đích danh',
	(bool) preg_grep( '/Ngày 11, N\.Kiệt \(LT\).*9:50/u', (array) $r_don['canh'] ), $r_don['canh'] );
t( '   và KHÔNG gán nhầm cho cả ngày là ngày lễ',
	0 === count( preg_grep( '/Ngày 11: bảng gốc tính GẤP/u', (array) $r_don['canh'] ) ), $r_don['canh'] );

/* ── ngày lễ mà có MỘT ô lạc hệ số -> vẫn phải gọi đích danh ô ấy.
   ⚠️ Phải dùng ngày 11: ngày ấy có BA cụm có giờ, đủ để hai ô cùng hệ số làm nên "cả ngày".
      Ngày 3 chỉ có hai cụm, nên một ô x2 + một ô x3 ra hoà — không thành ngày lễ, và nhánh cần
      canh không bao giờ chạy. Bản đầu của bài này dùng ngày 3, và phá thử cho thấy nó xanh
      vô ích. */
$tron = $B;
$tron[5][3]  = '21:40';          // Ngân        10:50 x2
$tron[5][15] = '09:00';          // N.Kiệt      4:30  x2
$tron[5][12] = '14:45';          // N.Kiệt (LT) 4:55  x3 — lạc loài giữa ngày x2
$r_tron = VHCC_NapDoc::doc( $tron );
t( 'ngày lễ x2 (hai ô) -> nhận ra hệ số trội',
	(bool) preg_grep( '/Ngày 11: bảng gốc tính GẤP 2/u', (array) $r_tron['canh'] ), $r_tron['canh'] );
t( '🔴 nhưng ô x3 lạc loài vẫn bị gọi đích danh',
	(bool) preg_grep( '/Ngày 11, N\.Kiệt \(LT\).*14:45/u', (array) $r_tron['canh'] ), $r_tron['canh'] );

/* ═════════════════════════════════════════════════════════════════════════════════════════
   6. TÊN GHI VÀO SỔ — anh Thắng 21/09/2026: *"nạp vào tên hệ thống tự do hay sao, có cần sửa
      tên đúng tên trên bản chấm công không"*
   ═════════════════════════════════════════════════════════════════════════════════════════
   Không cần sửa gì. Nhãn ở bảng ("N.Kiệt") chỉ để CHỌN người; thứ đi vào sổ là MÃ NV kèm họ
   tên đầy đủ lấy từ HỒ SƠ. Bài này chốt đúng câu trả lời ấy, để nó không lặng lẽ đổi sau này.
   ───────────────────────────────────────────────────────────────────────────────────────── */

$CS3 = 'NAPDOC_CS3';
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'NT1', 'ho_ten' => 'Nguyễn Tuấn Kiệt',
	'cua_hang' => $CS3, 'vai_tro' => 'Nhân viên', 'pin_dang_nhap' => '',
	'chuc_vu' => 'Nhân viên quầy', 'trang_thai_lam_viec' => 'Đang làm' ) );
/* Hồ sơ BỎ TRỐNG họ tên — có thật khi nạp .csv hồ sơ thiếu cột tên. */
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'NT2', 'ho_ten' => '',
	'cua_hang' => $CS3, 'vai_tro' => 'Nhân viên', 'pin_dang_nhap' => '',
	'chuc_vu' => 'Nhân viên quầy', 'trang_thai_lam_viec' => 'Đang làm' ) );
VHCC_NapDoc::nap( $AD, $CS3, $B, array( 'N.Kiệt' => 'NT1', 'Ngân' => 'NT2' ), false );

$ten_so = function ( $ma ) use ( $wpdb, $CS3 ) {
	return (string) $wpdb->get_var( $wpdb->prepare( 'SELECT ho_ten FROM ' . VHCC_DB::t( 'cham_cong' )
		. ' WHERE coso=%s AND ma_nv=%s LIMIT 1', $CS3, $ma ) );
};
t( '🔴 sổ ghi HỌ TÊN TRONG HỒ SƠ, không ghi nhãn viết tắt của bảng',
	'Nguyễn Tuấn Kiệt' === $ten_so( 'NT1' ), $ten_so( 'NT1' ) );
/* ⚠️ `isset()` không bắt được chuỗi rỗng, nên bản đầu ghi một cái tên TRẮNG vào bảng công —
   hàng có mã, có giờ, chỉ thiếu tên, nhìn vào tưởng hỏng dữ liệu chứ không ai nghĩ hồ sơ
   thiếu tên. Có nhãn còn hơn có ô trắng. */
t( '🔴 hồ sơ trống họ tên -> lấy tạm nhãn ở bảng, KHÔNG ghi tên trắng',
	'Ngân' === $ten_so( 'NT2' ), '[' . $ten_so( 'NT2' ) . ']' );

/* Đổi nhãn ở bảng (tháng sau gõ khác) KHÔNG được đẻ ra người thứ hai. */
$doi = $B;
foreach ( $doi[1] as $c => $o ) { $doi[1][ $c ] = str_replace( 'N.Kiệt', 'Kiệt', (string) $o ); }
$r_doi = VHCC_NapDoc::nap( $AD, $CS3, $doi, array(), true );
/* ⚠️ Hỏi ĐÍCH DANH cái nhãn mới, đừng hỏi con số tổng. Cơ sở này còn "K.Oanh" chưa ghép từ
   đầu (bài chỉ ghép N.Kiệt và Ngân), nên đếm tổng ra 2 chứ không phải 1 — và một phép thử
   đếm tổng vừa sai kỳ vọng vừa không nói được điều nó định nói. */
$ma_doi = null;
foreach ( $r_doi['nguoi'] as $n ) { if ( 'Kiệt' === $n['ten'] ) { $ma_doi = $n['ma']; } }
t( 'đổi nhãn ở bảng -> nhãn mới hiện ra là CHƯA ghép, không tự nhận bừa',
	'' === $ma_doi, $r_doi['nguoi'] );
t( '   và nhãn cũ "N.Kiệt" không còn trong bảng nữa',
	! in_array( 'N.Kiệt', array_column( $r_doi['nguoi'], 'ten' ), true ), $r_doi['nguoi'] );
$goi_doi = array();
foreach ( $r_doi['nguoi'] as $n ) { if ( 'Kiệt' === $n['ten'] ) { $goi_doi = $n['goiY']; } }
t( '   và vẫn gợi ý đúng người cũ', array( 'NT1' ) === $goi_doi, $goi_doi );
VHCC_NapDoc::nap( $AD, $CS3, $doi, array( 'Kiệt' => 'NT1' ), false );
$so_ma = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(DISTINCT ma_nv) FROM '
	. VHCC_DB::t( 'cham_cong' ) . ' WHERE coso=%s', $CS3 ) );
t( '🔴 ghép nhãn mới vào mã cũ -> KHÔNG đẻ ra người thứ hai', 2 === $so_ma, $so_ma );

/* ═════════════════════════════════════════════════════════════════════════════════════════
   7. THÁNG ĐỌC TỪ ĐÂU — anh Thắng 21/09/2026: *"hệ có tự biết tháng không"*
   ═════════════════════════════════════════════════════════════════════════════════════════
   Có: đọc thẳng từ dòng tiêu đề, không có ô chọn tháng trên form. Nhưng tiêu đề GÕ TAY nên
   gõ sai được, và chính tệp anh gửi có hai bảng cùng đề "THÁNG 8/2026". Vì vậy phải chắc ba
   chuyện: đọc đúng khi tiêu đề đúng, CHỐI khi không có tiêu đề (đừng đoán), và in ra đúng
   cách bảng đang viết để người ta soi lại được bằng mắt.
   ───────────────────────────────────────────────────────────────────────────────────────── */

t( 'đọc đúng tháng từ tiêu đề', '2026-04' === VHCC_NapDoc::doc( $B )['thang'] );
/* Tiêu đề lùi xuống dưới vài dòng trống vẫn phải thấy — bản đầu chỉ quét 6 dòng đầu. */
$lui = array_merge( array( array( '' ), array( '' ), array( '' ), array( '' ),
	array( '' ), array( '' ), array( '' ), array( '' ) ), $B );
t( 'tiêu đề nằm sau 8 dòng trống vẫn đọc được',
	'2026-04' === VHCC_NapDoc::doc( $lui )['thang'], VHCC_NapDoc::doc( $lui ) );

/* 🔴 KHÔNG CÓ TIÊU ĐỀ THÌ CHỐI, TUYỆT ĐỐI KHÔNG ĐOÁN THEO THÁNG HIỆN TẠI.
   Đoán là cả bảng rơi vào một tháng không ai chọn, và rơi im lặng — bảng vẫn đầy số. */
$khong = $B;
$khong[0] = array( 'Bảng chấm công' );
$r_khong = VHCC_NapDoc::doc( $khong );
t( '🔴 tiêu đề KHÔNG có tháng -> chối, không đoán', empty( $r_khong['ok'] ), $r_khong );
t( '   và bảo phải ghi tiêu đề thế nào',
	(bool) preg_grep( '/BẢNG CHẤM CÔNG THÁNG/u', (array) $r_khong['canh'] ), $r_khong['canh'] );
/* Tháng 13 là gõ sai -> cũng chối, đừng cuộn vòng thành tháng 1 năm sau. */
$muoi_ba = $B;
$muoi_ba[0] = array( 'BẢNG CHẤM CÔNG THÁNG 13/2026' );
t( '🔴 tháng 13 -> chối, không cuộn vòng', empty( VHCC_NapDoc::doc( $muoi_ba )['ok'] ) );

/* In ra ĐÚNG KIỂU BẢNG ĐANG VIẾT, để soi bằng mắt không phải dịch trong đầu. */
t( 'in tháng theo kiểu bảng gốc: 8/2026', '8/2026' === VHCC_NapDoc::thang_chu( '2026-08' ),
	VHCC_NapDoc::thang_chu( '2026-08' ) );
t( '   bỏ số 0 đứng đầu', '4/2026' === VHCC_NapDoc::thang_chu( '2026-04' ),
	VHCC_NapDoc::thang_chu( '2026-04' ) );
t( '   chuỗi lạ thì trả nguyên, không bịa', 'abc' === VHCC_NapDoc::thang_chu( 'abc' ) );

echo "\n";
if ( $truot ) {
	echo 'TRƯỢT ' . count( $truot ) . ":\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "ĐẠT: $dat phép thử — hai ca cách quãng KHÔNG bị nối thẳng thành giờ dư.\n";
