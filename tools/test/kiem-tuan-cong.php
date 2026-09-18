<?php
/**
 * KIỂM QUY TRÌNH SỬA BẢNG CÔNG THEO TUẦN — tải .xlsx, sửa, gửi, kế toán duyệt và khoá.
 *
 * =================================================================================================
 * 🔴 VÌ SAO BÀI NÀY CANH NẶNG NHẤT TRONG CẢ HỆ CHẤM CÔNG
 * =================================================================================================
 * Anh Thắng 18/09/2026 dựng ra quy trình này, và chốt: *"chỉ duyệt và gửi 1 lần) nên cần đảm bảo
 * chính xác"*. Một lượt duyệt sửa hàng trăm ô giờ công cùng lúc, không quay lại được, rồi khoá
 * tuần. Bốn chỗ hỏng, xếp theo mức đắt:
 *
 *   1. GHÉP NHẦM NGƯỜI — tệp đi ra khỏi hệ rồi quay về. Ghép lại theo tên là giờ của người này
 *      chui sang người kia, im lặng, tới kỳ lương mới lộ. Cột KHOÁ sinh ra để chặn đúng việc ấy.
 *   2. NẠP LÀ GHI — nếu nạp tệp mà ghi thẳng vào bảng công thì cửa hàng trưởng vừa lấy lại được
 *      đúng cái quyền `sua_gio` mà 18/09 vừa thu. Nạp chỉ được đẻ ra một cái đơn.
 *   3. DUYỆT HAI LẦN — bấm hai lần trên hai tab, hoặc hai đơn cùng tuần, là ghi hai lượt.
 *   4. ĐI TẮT QUANH `VHCC_Bu` — ghi thẳng `UPDATE cham_cong` thì mất nhật ký, mất chốt cơ sở,
 *      mất chốt không-tự-sửa-giờ-mình. Hàng trăm ô một lượt là chỗ cuối cùng được phép đi tắt.
 *
 * Chạy: php tools/test/kiem-tuan-cong.php
 */

$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
vhcc_test_boot( $goc . '/wordpress/vhcp-cham-cong' );

$dat = 0; $truot = array();

register_shutdown_function( function () {
	global $dat, $truot;
	$e = error_get_last();
	if ( ! $e || ! in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) { return; }
	echo "\n🔴 BÀI KIỂM CHẾT GIỮA ĐƯỜNG: " . $e['message'] . "\n   tại " . $e['file'] . ':' . $e['line'] . "\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT (tới lúc chết): $dat\n";
} );

function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( null === $them ? '' : "\n      → " . ( is_scalar( $them ) ? $them : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) );
}
function teq( $ten, $mong, $thuc ) {
	t( $ten . ' (mong ' . json_encode( $mong, JSON_UNESCAPED_UNICODE ) . ')', $mong === $thuc, $thuc );
}

global $wpdb;

$CS_A  = 'TC_SHOP_A';
$CS_B  = 'TC_SHOP_B';
$TUAN  = VHCC_TuanCong::tuan_truoc();
$NGAY  = VHCC_TuanCong::bay_ngay( $TUAN );

$NV    = array( 'name' => 'Nhân Viên',  'role' => VHCC_Vai::NV,      'coso' => $CS_A, 'ma_nv' => 'TCNV1' );
$CHT_A = array( 'name' => 'Trưởng A',   'role' => VHCC_Vai::CHT,     'coso' => $CS_A, 'ma_nv' => 'TCCHTA' );
$CHT_B = array( 'name' => 'Trưởng B',   'role' => VHCC_Vai::CHT,     'coso' => $CS_B, 'ma_nv' => 'TCCHTB' );
$KT    = array( 'name' => 'Kế Toán',    'role' => VHCC_Vai::KE_TOAN, 'coso' => '',    'ma_nv' => 'TCKT1' );
$ADMIN = array( 'name' => 'Quản Trị',   'role' => VHCC_Vai::ADMIN,   'coso' => '',    'ma_nv' => 'TCAD1' );

foreach ( array(
	array( 'TCNV1',  'An Nhân Viên', $CS_A, 'Nhân viên' ),
	array( 'TCNV2',  'Bình Nhân Viên', $CS_A, 'Nhân viên' ),
	array( 'TCCHTA', 'Trưởng A',     $CS_A, 'Cửa hàng trưởng' ),
	array( 'TCCHTB', 'Trưởng B',     $CS_B, 'Cửa hàng trưởng' ),
	array( 'TCKT1',  'Kế Toán',      $CS_A, 'Kế toán' ),
) as $x ) {
	$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => $x[0], 'ho_ten' => $x[1],
		'cua_hang' => $x[2], 'vai_tro' => $x[3], 'trang_thai_lam_viec' => 'Đang làm' ) );
}

/* Gieo công: TCNV1 làm T2 và T3, TCNV2 chỉ T2. Còn lại để trống — chỗ để điền bù. */
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => $CS_A, 'ngay' => $NGAY[0],
	'ma_nv' => 'TCNV1', 'ho_ten' => 'An Nhân Viên', 'gio_vao_giay' => 28800, 'gio_ra_giay' => 61200,
	'hau_to' => '', 'nguon' => 'may' ) );
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => $CS_A, 'ngay' => $NGAY[1],
	'ma_nv' => 'TCNV1', 'ho_ten' => 'An Nhân Viên', 'gio_vao_giay' => 28800, 'gio_ra_giay' => 57600,
	'hau_to' => '', 'nguon' => 'may' ) );
$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array( 'coso' => $CS_A, 'ngay' => $NGAY[0],
	'ma_nv' => 'TCNV2', 'ho_ten' => 'Bình Nhân Viên', 'gio_vao_giay' => 32400, 'gio_ra_giay' => 61200,
	'hau_to' => '', 'nguon' => 'may' ) );

/* ================================================================= tuần T2 → CN */

echo "— tuần —\n";
teq( 'thứ Hai của tuần là chính nó', $TUAN, VHCC_TuanCong::thu_hai( $TUAN ) );
teq( 'ngày nào trong tuần cũng ra cùng một thứ Hai', $TUAN, VHCC_TuanCong::thu_hai( $NGAY[3] ) );
/* 🔴 CHỦ NHẬT PHẢI THUỘC TUẦN ẤY, không phải mở tuần sau. Sai chỗ này là mọi đêm Chủ nhật
   bảng công nhảy sang tuần khác — lỗi chỉ hiện một ngày trong bảy. */
teq( '🔴 Chủ nhật vẫn thuộc tuần bắt đầu thứ Hai ấy', $TUAN, VHCC_TuanCong::thu_hai( $NGAY[6] ) );
teq( 'bảy ngày, không hơn không kém', 7, count( $NGAY ) );
teq( 'ngày đầu là thứ Hai', '1', gmdate( 'N', strtotime( $NGAY[0] . ' 00:00:00 UTC' ) ) );
teq( 'ngày cuối là Chủ nhật', '7', gmdate( 'N', strtotime( $NGAY[6] . ' 00:00:00 UTC' ) ) );
teq( 'chu_nhat() khớp ngày thứ bảy của mảng', $NGAY[6], VHCC_TuanCong::chu_nhat( $TUAN ) );
t( 'ds_tuan không bao giờ kể tuần đang chạy',
	! in_array( VHCC_TuanCong::thu_hai( (string) current_time( 'Y-m-d' ) ), VHCC_TuanCong::ds_tuan( 8 ), true ) );
teq( 'ngày rác thì trả rỗng', '', VHCC_TuanCong::thu_hai( 'hom-qua' ) );

/* ================================================================= cột KHOÁ */

echo "— khoá dòng —\n";
/* Từ bản ngang 18/09/2026, khoá gắn với DÒNG (một người) chứ không với từng ô — ngày đọc từ
   dòng tiêu đề. Xem khối bố cục tờ ở `VHCC_TuanCong`. */
$k = VHCC_TuanCong::khoa_dong( $CS_A, $TUAN, '', 'TCNV1' );
$d = VHCC_TuanCong::doc_khoa( $k, $CS_A, $TUAN );
t( 'khoá đọc ngược ra được', is_array( $d ), $d );
teq( 'đúng mã NV', 'TCNV1',  $d['ma_nv'] );
t( 'khoá KHÔNG còn mang ngày — ngày nằm ở dòng tiêu đề', ! isset( $d['ngay'] ) );

/* 🔴 BA CÁCH TỆP BỊ DÙNG SAI, CẢ BA PHẢI CHỐI. */
t( '🔴 sửa tay vào cột khoá thì chối', null === VHCC_TuanCong::doc_khoa( $k . 'x', $CS_A, $TUAN ) );
t( '🔴 khoá của CƠ SỞ KHÁC thì chối', null === VHCC_TuanCong::doc_khoa( $k, $CS_B, $TUAN ) );
$tuan_khac = gmdate( 'Y-m-d', strtotime( $TUAN . ' 00:00:00 UTC' ) - 7 * 86400 );
t( '🔴 khoá của TUẦN KHÁC thì chối', null === VHCC_TuanCong::doc_khoa( $k, $CS_A, $tuan_khac ) );
t( 'khoá rỗng thì chối', null === VHCC_TuanCong::doc_khoa( '', $CS_A, $TUAN ) );
t( 'khoá thiếu mảnh thì chối', null === VHCC_TuanCong::doc_khoa( 'TCNV1', $CS_A, $TUAN ) );

$k2 = VHCC_TuanCong::khoa_dong( $CS_A, $TUAN, '', 'TCNV1', 'CD' );
$d2 = VHCC_TuanCong::doc_khoa( $k2, $CS_A, $TUAN );
teq( 'hậu tố (ca đêm) đi theo khoá', 'CD', $d2['hau_to'] );
t( 'và khoá có hậu tố khác khoá không hậu tố', $k !== $k2 );

/* ================================================================= đọc ô giờ */

echo "— đọc ô giờ —\n";
teq( "gõ '08:00'", '08:00', VHCC_TuanCong::doc_gio( '08:00' ) );
teq( "Excel thêm giây", '08:00', VHCC_TuanCong::doc_gio( '08:00:00' ) );
teq( 'gõ liền 4 số', '08:00', VHCC_TuanCong::doc_gio( '0800' ) );
teq( 'một chữ số giờ', '08:05', VHCC_TuanCong::doc_gio( '8:05' ) );
/* 🔴 Ô ĐỊNH DẠNG "THỜI GIAN" TRONG EXCEL LƯU RA PHÂN SỐ CỦA MỘT NGÀY. Không hiểu nhánh này thì
   mọi ô giờ người ta gõ lại đều thành rác và cả tệp bị chối mà không ai hiểu vì sao. */
teq( '🔴 phân số ngày kiểu Excel: 0.5 = 12:00', '12:00', VHCC_TuanCong::doc_gio( '0.5' ) );
teq( '🔴 0.3333333 ≈ 08:00', '08:00', VHCC_TuanCong::doc_gio( '0.3333333' ) );
teq( 'dấu phẩy thập phân cũng ăn', '12:00', VHCC_TuanCong::doc_gio( '0,5' ) );
teq( 'ô trống = xoá giờ, không phải rác', '', VHCC_TuanCong::doc_gio( '' ) );
teq( 'ô toàn khoảng trắng cũng là trống', '', VHCC_TuanCong::doc_gio( '   ' ) );
t( 'chữ rác thì trả null', null === VHCC_TuanCong::doc_gio( 'sáng' ) );
t( 'giờ quá 23 thì null', null === VHCC_TuanCong::doc_gio( '25:00' ) );
t( 'phút quá 59 thì null', null === VHCC_TuanCong::doc_gio( '08:75' ) );

/* ================================================================= ai tải được */

echo "— ai tải được —\n";
t( '🔴 nhân viên thường KHÔNG tải được',
	'' !== VHCC_TuanCong::vi_sao_khong_tai( $NV, $CS_A, $TUAN ) );
t( '🔴 trưởng B KHÔNG tải được tuần của cơ sở A',
	'' !== VHCC_TuanCong::vi_sao_khong_tai( $CHT_B, $CS_A, $TUAN ) );
teq( 'trưởng A tải được tuần trước của cơ sở mình', '',
	VHCC_TuanCong::vi_sao_khong_tai( $CHT_A, $CS_A, $TUAN ) );

/* 🔴 TUẦN ĐANG CHẠY KHÔNG TẢI ĐƯỢC. Anh Thắng: *"sau hết 1 tuần, bắt đầu tuần mới"*. */
$tuan_nay = VHCC_TuanCong::thu_hai( (string) current_time( 'Y-m-d' ) );
$chan = VHCC_TuanCong::vi_sao_khong_tai( $CHT_A, $CS_A, $tuan_nay );
t( '🔴 tuần ĐANG CHẠY thì chưa tải được', '' !== $chan, $chan );
t( 'và nói rõ phải chờ hết tuần', false !== mb_strpos( $chan, 'chưa kết thúc' ), $chan );

t( 'ngày giữa tuần (không phải thứ Hai) thì chối',
	'' !== VHCC_TuanCong::vi_sao_khong_tai( $CHT_A, $CS_A, $NGAY[2] ) );

/* ================================================================= dữ liệu tuần */

echo "— dữ liệu tuần —\n";
$hang = VHCC_TuanCong::hang_tuan( $CS_A, $TUAN );
/* Bốn người của cơ sở A (TCNV1, TCNV2, TCCHTA, TCKT1) × 7 ngày. */
teq( '🔴 bày CẢ ô trống: 4 người × 7 ngày', 28, count( $hang ) );

$tra = array();
foreach ( $hang as $r ) { $tra[ $r['ma_nv'] . '|' . $r['ngay'] ] = $r; }
teq( 'giờ vào của TCNV1 ngày T2', '08:00', $tra[ 'TCNV1|' . $NGAY[0] ]['vao'] );
teq( 'giờ ra tương ứng',           '17:00', $tra[ 'TCNV1|' . $NGAY[0] ]['ra'] );
teq( 'số giờ tính đúng',           9.0,     $tra[ 'TCNV1|' . $NGAY[0] ]['gio'] );
teq( 'ngày chưa chấm thì để trống', '',     $tra[ 'TCNV1|' . $NGAY[4] ]['vao'] );
t( 'ngày chưa chấm thì số giờ là null', null === $tra[ 'TCNV1|' . $NGAY[4] ]['gio'] );
t( '🔴 KHÔNG lẫn người của cơ sở B', ! isset( $tra[ 'TCCHTB|' . $NGAY[0] ] ) );

/* ================================================================= vòng tròn xuất → đọc */

if ( ! class_exists( 'ZipArchive' ) ) {
	echo "\n(bỏ qua phần tệp .xlsx: máy chạy bộ thử không có php-zip)\n";
} else {

echo "— xuất rồi nạp lại —\n";
$tam = array();
function tep_tu( $noi_dung ) {
	global $tam;
	$d = tempnam( sys_get_temp_dir(), 'kiemtuan' );
	file_put_contents( $d, $noi_dung );
	$tam[] = $d;
	return $d;
}

$x = VHCC_TuanCong::xuat( $CHT_A, $CS_A, $TUAN );
t( 'trưởng A xuất được tệp', ! empty( $x['ok'] ), $x );
teq( '🔴 bốn người là BỐN dòng, không phải 28 — ngày nằm ngang', 4, $x['soDong'] );
t( 'tên tệp mang cơ sở và tuần',
	false !== strpos( $x['ten'], $CS_A ) && false !== strpos( $x['ten'], $TUAN ), $x['ten'] );

$doc = VHCC_DocXlsx::doc( tep_tu( $x['noi_dung'] ) );
t( 'đọc lại được tệp vừa xuất', ! empty( $doc['ok'] ), $doc );
teq( 'gồm cả dòng tiêu đề', 5, count( $doc['hang'] ) );
teq( 'tờ rộng đúng 19 cột', 19, count( $doc['hang'][0] ) );
/* 🔴 Ô tiêu đề ngày PHẢI mang ngày dạng YYYY-MM-DD — đó là thứ lúc đọc dò ngược ra, thay cho
   việc đếm vị trí cột. Mất nó là chèn một cột ghi chú làm lệch hết giờ sang ngày bên cạnh. */
t( '🔴 tiêu đề cột ngày mang sẵn ngày ISO',
	false !== strpos( (string) $doc['hang'][0][ VHCC_TuanCong::C_NGAY_DAU ], $NGAY[0] ),
	$doc['hang'][0][ VHCC_TuanCong::C_NGAY_DAU ] );
t( 'và nói rõ ô nào là vào, ô nào là ra',
	false !== strpos( (string) $doc['hang'][0][ VHCC_TuanCong::C_NGAY_DAU ], 'vào' )
	&& false !== strpos( (string) $doc['hang'][0][ VHCC_TuanCong::C_NGAY_DAU + 1 ], ' ra' ) );
teq( 'cột cuối là cột KHOÁ', 'KHOÁ — ĐỪNG SỬA', $doc['hang'][0][ VHCC_TuanCong::C_KHOA ] );
teq( 'và cột Lý do đứng ngay trước nó', 'Lý do sửa', $doc['hang'][0][ VHCC_TuanCong::C_LYDO ] );

/* 🔴 KHÔNG SỬA GÌ THÌ KHÔNG CÓ ĐƠN. Gửi lại y nguyên tệp vừa tải mà đẻ ra một đơn rỗng thì kế
   toán phải ngồi duyệt những đơn không đổi gì cả. */
$r = VHCC_TuanCong::doi( $CS_A, $TUAN, $doc['hang'] );
t( 'tệp y nguyên thì đối chiếu vẫn chạy', ! empty( $r['ok'] ), $r );
teq( '🔴 và KHÔNG có ô nào đổi', 0, count( $r['doi'] ) );

$r = VHCC_TuanCong::nap( $CHT_A, $CS_A, $TUAN, tep_tu( $x['noi_dung'] ) );
t( '🔴 nạp tệp không đổi gì thì chối, không đẻ đơn rỗng', empty( $r['ok'] ), $r );

/* ---- sửa một ô rồi nạp ---- */
/** Sửa ô vào/ra của MỘT NGÀY trên dòng của một người — dò cột theo dòng tiêu đề, y như mã thật. */
function sua_o( $hang, $khoa, $ngay, $vao, $ra, $ly_do ) {
	$cv = -1; $cr = -1;
	foreach ( $hang[0] as $i_c => $o ) {
		if ( false === strpos( (string) $o, $ngay ) ) { continue; }
		if ( false !== mb_strpos( (string) $o, ' ra' ) ) { $cr = $i_c; } else { $cv = $i_c; }
	}
	foreach ( $hang as $i => $d ) {
		if ( ! isset( $d[ VHCC_TuanCong::C_KHOA ] ) || $d[ VHCC_TuanCong::C_KHOA ] !== $khoa ) { continue; }
		if ( $cv >= 0 ) { $hang[ $i ][ $cv ] = $vao; }
		if ( $cr >= 0 ) { $hang[ $i ][ $cr ] = $ra; }
		if ( '' !== $ly_do ) { $hang[ $i ][ VHCC_TuanCong::C_LYDO ] = $ly_do; }
		return $hang;
	}
	return $hang;
}
function ghi_tep( $hang ) {
	$h = array();
	foreach ( $hang as $d ) {
		$mot = array();
		foreach ( $d as $o ) { $mot[] = VHCC_Xuat::chu( (string) $o ); }
		$h[] = $mot;
	}
	return tep_tu( VHCC_Xuat::xlsx( array( array( 'ten' => 'Tuan', 'hang' => $h ) ) ) );
}

$khoa_nv1 = VHCC_TuanCong::khoa_dong( $CS_A, $TUAN, '', 'TCNV1' );
$sua = sua_o( $doc['hang'], $khoa_nv1, $NGAY[0], '08:30', '17:00', 'máy lệch đồng hồ, đối chiếu camera' );

$r = VHCC_TuanCong::doi( $CS_A, $TUAN, $sua );
teq( 'sửa một ô thì đối chiếu ra đúng một ô đổi', 1, count( $r['doi'] ) );
teq( 'ghi đúng giờ cũ', '08:00', $r['doi'][0]['vaoCu'] );
teq( 'và giờ mới',      '08:30', $r['doi'][0]['vao'] );
teq( 'ô đã có giờ thì KHÔNG phải dòng bù', false, $r['doi'][0]['them'] );

/* 🔴 SỬA MÀ KHÔNG GHI LÝ DO THÌ CHỐI. Lý do là thứ duy nhất còn tra ngược được sau ba tháng. */
$khong_ly_do = sua_o( $doc['hang'], $khoa_nv1, $NGAY[0], '08:30', '17:00', '' );
$r = VHCC_TuanCong::doi( $CS_A, $TUAN, $khong_ly_do );
t( '🔴 sửa giờ mà bỏ trống Lý do thì chối cả tệp', empty( $r['ok'] ), $r );
t( 'và nói rõ dòng nào', false !== mb_strpos( $r['error'], 'Lý do' ), $r['error'] );

/* 🔴 SỬA TAY VÀO CỘT KHOÁ THÌ CHỐI CẢ TỆP. */
$pha = $doc['hang'];
$pha[1][ VHCC_TuanCong::C_KHOA ] = $pha[1][ VHCC_TuanCong::C_KHOA ] . 'z';
$r = VHCC_TuanCong::doi( $CS_A, $TUAN, $pha );
t( '🔴 cột KHOÁ bị sửa thì chối cả tệp', empty( $r['ok'] ), $r );
t( 'và chỉ đúng dòng', false !== mb_strpos( $r['error'], 'Dòng 2' ), $r['error'] );

/* 🔴 DÁN TỪ TUẦN KHÁC SANG. Đây là tai nạn hay gặp nhất — tải hai tuần rồi sửa nhầm tệp. */
$khac = $doc['hang'];
$khac[1][ VHCC_TuanCong::C_KHOA ] = VHCC_TuanCong::khoa_dong( $CS_A, $tuan_khac, '', 'TCNV1' );
$r = VHCC_TuanCong::doi( $CS_A, $TUAN, $khac );
t( '🔴 dán khoá của tuần khác thì chối', empty( $r['ok'] ), $r );

/* 🔴 CHÈN THÊM MỘT CỘT GHI CHÚ Ở GIỮA — đây là lý do ngày phải đọc từ dòng tiêu đề chứ không
   đếm theo vị trí. Người ta hay chèn một cột để ghi chú riêng rồi gửi nguyên thế. Đếm vị trí
   thì mọi giờ từ đó trở đi lệch sang ngày bên cạnh, im lặng, và kế toán duyệt luôn. */
$chen = array();
foreach ( $sua as $i_d => $d ) {
	$m = array_values( $d );
	array_splice( $m, VHCC_TuanCong::C_NGAY_DAU, 0, array( 0 === $i_d ? 'ghi chú riêng' : 'abc' ) );
	$chen[] = $m;
}
$r = VHCC_TuanCong::doi( $CS_A, $TUAN, $chen );
t( '🔴 chèn thêm một cột giữa tờ thì vẫn đọc đúng', ! empty( $r['ok'] ), $r );
teq( 'và vẫn ra đúng một ô đổi, không lệch ngày', 1, count( $r['doi'] ) );
teq( 'đúng ngày đã sửa', $NGAY[0], $r['doi'][0]['ngay'] );

/* 🔴 XOÁ MẤT MỘT CỘT NGÀY thì CHỐI, không lặng lẽ bỏ qua ngày ấy. */
$thieu = array();
foreach ( $sua as $d ) {
	$m = array_values( $d );
	array_splice( $m, VHCC_TuanCong::C_NGAY_DAU, 2 );
	$thieu[] = $m;
}
$r = VHCC_TuanCong::doi( $CS_A, $TUAN, $thieu );
t( '🔴 thiếu cột của một ngày thì chối', empty( $r['ok'] ), $r );
t( 'và nói rõ thiếu ngày nào', false !== strpos( $r['error'], $NGAY[0] ), $r['error'] );

/* Tệp không đúng mẫu — thiếu cột KHOÁ. */
$r = VHCC_TuanCong::doi( $CS_A, $TUAN, array( array( 'Mã NV', 'Họ tên' ), array( 'X', 'Y' ) ) );
t( 'tệp tự dựng (không có cột KHOÁ) thì chối', empty( $r['ok'] ), $r );
t( 'và bảo tải lại tệp mẫu', false !== mb_strpos( $r['error'], 'mẫu' ), $r['error'] );

/* ---- nạp thật ---- */
echo "— nạp và duyệt —\n";
$truoc_cham = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'cham_cong' ) );
$r = VHCC_TuanCong::nap( $CHT_A, $CS_A, $TUAN, ghi_tep( $sua ) );
t( 'nạp được', ! empty( $r['ok'] ), $r );
teq( 'đơn ghi đúng một ô đổi', 1, $r['soDoi'] );
$don_id = (int) $r['id'];

/* 🔴 NẠP KHÔNG ĐƯỢC CHẠM VÀO BẢNG CÔNG. Đây là chốt giữ cho cửa hàng trưởng không lấy lại được
   quyền sửa giờ bằng đường vòng. */
teq( '🔴 nạp xong bảng công chưa đổi một ô nào', '08:00',
	VHCC_Bu::gio_hien_tai( $CS_A, $NGAY[0], 'TCNV1' )['vao'] );
teq( '🔴 và không đẻ thêm dòng chấm công nào', $truoc_cham,
	(int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'cham_cong' ) ) );
t( '🔴 tuần vẫn CHƯA khoá khi đơn mới chỉ nằm chờ',
	! VHCC_TuanCong::khoa_roi( $CS_A, $TUAN ) );

/* Gửi lượt mới thì lượt chờ cũ phải bị thay, không để hai đơn cùng chờ. */
$sua2 = sua_o( $doc['hang'], $khoa_nv1, $NGAY[0], '08:45', '17:00', 'đối chiếu lại camera lần hai' );
$r2 = VHCC_TuanCong::nap( $CHT_A, $CS_A, $TUAN, ghi_tep( $sua2 ) );
t( 'gửi lại tệp khác thì nạp được', ! empty( $r2['ok'] ), $r2 );
teq( '🔴 lượt chờ cũ bị thay, chỉ còn MỘT đơn chờ cho tuần ấy', 1,
	(int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . VHCC_DB::t( 'don_tuan' )
		. ' WHERE coso=%s AND tu_ngay=%s AND trang_thai=%s', $CS_A, $TUAN, VHCC_TuanCong::CHO ) ) );
teq( 'và đơn chờ là lượt MỚI', (int) $r2['id'], (int) VHCC_TuanCong::don_cho( $CS_A, $TUAN )['id'] );

/* ---- ai duyệt được ---- */
$r = VHCC_TuanCong::duyet( $CHT_A, $r2['id'], true );
t( '🔴 cửa hàng trưởng KHÔNG tự duyệt đơn mình gửi', empty( $r['ok'] ), $r );
$r = VHCC_TuanCong::duyet( $NV, $r2['id'], true );
t( '🔴 nhân viên càng không', empty( $r['ok'] ), $r );

/* ---- chối ---- */
$r = VHCC_TuanCong::duyet( $KT, $r2['id'], false, 'x' );
t( '🔴 chối mà không nói vì sao thì không cho', empty( $r['ok'] ), $r );
$r = VHCC_TuanCong::duyet( $KT, $r2['id'], false, 'giờ ra ngày T3 chưa khớp camera' );
t( 'kế toán chối được, kèm lý do', ! empty( $r['ok'] ), $r );
t( '🔴 chối thì tuần KHÔNG khoá — cửa hàng trưởng còn gửi lại được',
	! VHCC_TuanCong::khoa_roi( $CS_A, $TUAN ) );
teq( 'và bảng công vẫn nguyên', '08:00',
	VHCC_Bu::gio_hien_tai( $CS_A, $NGAY[0], 'TCNV1' )['vao'] );

/* ---- gửi lại rồi duyệt thật ---- */
/* Hai ngày khác nhau CỦA CÙNG MỘT NGƯỜI, trên cùng một dòng — chính là cái bố cục ngang
   làm được mà bố cục dọc thì phải sửa hai dòng rời. */
$sua3 = sua_o( $doc['hang'], $khoa_nv1, $NGAY[0], '08:30', '17:00', 'máy lệch đồng hồ, đối chiếu camera' );
$sua3 = sua_o( $sua3, $khoa_nv1, $NGAY[4], '09:00', '18:00', '' );
$r3 = VHCC_TuanCong::nap( $CHT_A, $CS_A, $TUAN, ghi_tep( $sua3 ) );
t( 'gửi lại sau khi bị chối', ! empty( $r3['ok'] ), $r3 );
teq( '🔴 một dòng, hai ngày đổi -> hai ô', 2, $r3['soDoi'] );

$r = VHCC_TuanCong::duyet( $KT, $r3['id'], true );
t( 'kế toán duyệt được', ! empty( $r['ok'] ), $r );
teq( '🔴 hai ô lên bảng công', 2, $r['xong'] );
teq( 'không ô nào trượt', 0, count( $r['truot'] ) );

/* 🔴 ĐÂY LÀ CHỖ CẢ QUY TRÌNH TỒN TẠI VÌ NÓ. */
teq( '🔴 ô đã có giờ được SỬA ĐÈ', '08:30',
	VHCC_Bu::gio_hien_tai( $CS_A, $NGAY[0], 'TCNV1' )['vao'] );
teq( '🔴 ô trống được BÙ vào', '09:00',
	VHCC_Bu::gio_hien_tai( $CS_A, $NGAY[4], 'TCNV1' )['vao'] );
teq( 'và giờ ra của ô bù cũng vào', '18:00',
	VHCC_Bu::gio_hien_tai( $CS_A, $NGAY[4], 'TCNV1' )['ra'] );

/* 🔴 ĐI QUA `VHCC_Bu` THẬT — kiểm bằng dấu vết nó để lại, không bằng lời hứa. */
$nk = VHCC_Bu::ds_nhat_ky( $ADMIN, $CS_A );
t( '🔴 mọi ô sửa đều vào nhật ký "đã động vào giờ công"', count( $nk ) > 0, count( $nk ) );
$co_ly_do = false;
foreach ( $nk as $x ) {
	if ( false !== mb_strpos( json_encode( $x, JSON_UNESCAPED_UNICODE ), 'Đơn tuần #' ) ) { $co_ly_do = true; }
}
t( '🔴 và nhật ký nói rõ ô ấy đến từ đơn tuần nào', $co_ly_do, $nk );

/* ---- khoá ---- */
t( '🔴 duyệt xong thì tuần KHOÁ', VHCC_TuanCong::khoa_roi( $CS_A, $TUAN ) );
$chan = VHCC_TuanCong::vi_sao_khong_tai( $CHT_A, $CS_A, $TUAN );
t( '🔴 tuần đã khoá thì cửa hàng trưởng không tải được nữa', '' !== $chan, $chan );
t( 'và câu chối bảo báo kế toán', false !== mb_strpos( $chan, 'kế toán' ), $chan );
/* Anh Thắng: *"admin có quyền tải nếu khóa"*. */
teq( '🔴 nhưng ADMIN vẫn tải được tuần đã khoá', '',
	VHCC_TuanCong::vi_sao_khong_tai( $ADMIN, $CS_A, $TUAN ) );

$r = VHCC_TuanCong::nap( $CHT_A, $CS_A, $TUAN, ghi_tep( $sua3 ) );
t( '🔴 và không nạp thêm tệp nào cho tuần đã khoá được', empty( $r['ok'] ), $r );

/* 🔴 DUYỆT HAI LẦN — bấm hai lần trên hai tab. */
$r = VHCC_TuanCong::duyet( $KT, $r3['id'], true );
t( '🔴 duyệt lại chính đơn ấy thì chối', empty( $r['ok'] ), $r );
t( 'và nói là đã xử rồi', false !== mb_strpos( $r['error'], 'đã xử' ), $r['error'] );

/* 🔴 CƠ SỞ KHÁC KHÔNG BỊ KHOÁ LÂY. */
t( '🔴 khoá tuần của cơ sở A không khoá lây sang cơ sở B',
	! VHCC_TuanCong::khoa_roi( $CS_B, $TUAN ) );

foreach ( $tam as $x ) { @unlink( $x ); }
}

/* ================================================================= không đi tắt */

echo "— không đi tắt —\n";
$src = '';
foreach ( token_get_all( file_get_contents(
	$goc . '/wordpress/vhcp-cham-cong/includes/class-vhcc-tuan-cong.php' ) ) as $tk ) {
	if ( is_array( $tk ) && in_array( $tk[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) { continue; }
	$src .= is_array( $tk ) ? $tk[1] : $tk;
}
/* 🔴 Lớp này KHÔNG được tự ghi vào bảng chấm công. Mọi ô phải đi qua `VHCC_Bu` để còn nhật ký,
   chốt cơ sở và chốt không-tự-sửa-giờ-mình. Đi tắt thì nhanh hơn, và mất cả ba. */
t( '🔴 KHÔNG có UPDATE/INSERT/DELETE nào lên bảng cham_cong',
	! preg_match( "#(UPDATE|INSERT|DELETE)[^;]*cham_cong#i", $src )
	&& false === strpos( $src, "VHCC_DB::t( 'cham_cong' ) ," ) );
t( '🔴 và có gọi VHCC_Bu::sua + VHCC_Bu::ghi',
	false !== strpos( $src, 'VHCC_Bu::sua(' ) && false !== strpos( $src, 'VHCC_Bu::ghi(' ) );
t( 'duyệt đòi đúng quyền sua_gio',
	VHCC_TuanCong::QUYEN_DUYET === 'sua_gio' );

echo "\n";
if ( $truot ) {
	echo '🔴 HỎNG ' . count( $truot ) . " phép thử:\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "✓ ĐẠT: $dat phép thử — tải, sửa, gửi, duyệt, khoá; và nạp KHÔNG chạm vào bảng công.\n";
