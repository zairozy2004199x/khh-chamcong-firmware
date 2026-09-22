<?php
/**
 * KIỂM ĐỐI SOÁT NGÂN HÀNG · SỔ CÔNG NỢ · QUÉT DÂY CHUYỀN CỦA JP CAPSULE.
 *
 * =============================================================================================
 * 🔴 BA MÀN NÀY ĐI TÌM ĐÚNG THỨ MÀ "SỔ CÂN" KHÔNG THẤY
 * =============================================================================================
 * Mọi bút toán JP sinh ra đều đủ hai vế theo cấu tạo, nên bảng cân đối gần như không chứng minh
 * được gì. Tiền bị ghi HAI LẦN thì cả hai vế cùng nhân đôi và sổ lại càng cân. Bài này canh đúng
 * mấy chỗ ấy:
 *   1. Một lô sao kê áp hai lần ⇒ tiền nhân đôi ở cột "đã nhận".
 *   2. Đổi tên tệp rồi áp lại ⇒ vẫn phải là CÙNG một lô.
 *   3. Dòng khớp từ hai cơ sở trở lên mà hệ tự chọn ⇒ tiền của A nằm trong sổ B.
 *   4. Huỷ lô mà đặt về 0 ⇒ xoá luôn phần người khác vừa ghi.
 *   5. Cổng "xác nhận cột" tắt mà luồng tự động vẫn ghi ⇒ ghi theo cột đọc sai.
 *   6. Bốn cột cách thu không cộng ra "đã nhận" ⇒ kế toán đi tìm một đồng không tồn tại.
 *   7. Hai kỳ chồng CHỈ SỐ ⇒ doanh thu và giá vốn vào sổ hai lượt.
 *
 * Chạy: php tools/test/kiem-jp-doi-soat.php
 */

require_once __DIR__ . '/wp-stub.php';

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $s ) { return rtrim( (string) $s, '/\\' ) . '/'; }
}
if ( ! function_exists( 'wp_mkdir_p' ) ) { function wp_mkdir_p( $d ) { return is_dir( $d ) || mkdir( $d, 0777, true ); } }
if ( ! function_exists( 'register_activation_hook' ) ) {
	function register_activation_hook( $t, $c ) { return true; }
}
if ( ! function_exists( 'register_deactivation_hook' ) ) {
	function register_deactivation_hook( $t, $c ) { return true; }
}
if ( ! function_exists( 'add_rewrite_rule' ) ) { function add_rewrite_rule() {} }
if ( ! function_exists( 'flush_rewrite_rules' ) ) { function flush_rewrite_rules( $x = true ) {} }
if ( ! function_exists( 'nocache_headers' ) ) { function nocache_headers() {} }
if ( ! function_exists( 'register_rest_route' ) ) { function register_rest_route() { return true; } }
if ( ! function_exists( 'wp_generate_password' ) ) {
	function wp_generate_password( $n = 12, $a = true, $b = false ) {
		$c = 'abcdefghijklmnopqrstuvwxyz0123456789'; $s = '';
		for ( $i = 0; $i < $n; $i++ ) { $s .= $c[ random_int( 0, strlen( $c ) - 1 ) ]; }
		return $s;
	}
}

$DAT = 0; $TRUOT = array();
function t( $ten, $dung, $them = null ) {
	global $DAT, $TRUOT;
	if ( $dung ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null === $them ? '' : ' — ' . ( is_scalar( $them ) ? $them : wp_json_encode( $them ) ) );
}
function ne( $f ) {
	try { return array( 'ok' => true, 'ra' => $f() ); }
	catch ( Throwable $e ) { return array( 'ok' => false, 'loi' => $e->getMessage() ); }
}

vhjp_test_boot( dirname( __DIR__, 2 ) . '/wordpress/vhcp-jp' );

$KT = array( 'id' => 'K1', 'hoTen' => 'Kế Toán', 'role' => VHJP_Auth::VAI_KT, 'locationIds' => array() );
$NV = array( 'id' => 'U1', 'hoTen' => 'Nhân Viên A', 'role' => VHJP_Auth::VAI_NV,
	'locationIds' => array( 'CS01' ) );

function nen() {
	vhjp_dung_bang();
	VHJP_Nguon::them( 'JP_Locations', array( 'id' => 'CS01', 'name' => 'JP Ba Ria', 'code' => 'BR',
		'maKH' => 'KHBR', 'maDinhDanh' => 'JPBR01', 'active' => 1 ) );
	VHJP_Nguon::them( 'JP_Locations', array( 'id' => 'CS02', 'name' => 'JP Vung Tau', 'code' => 'VT',
		'maKH' => 'KHVT', 'maDinhDanh' => 'JPVT01', 'active' => 1 ) );
	VHJP_Nguon::them( 'JP_Items', array( 'code' => 'H1', 'misa' => 'M1', 'name' => 'Trứng', 'active' => 1 ) );
	update_option( VHJP_DoiSoat::O_XAC_NHAN_COT, 0 );
	update_option( VHJP_DoiSoat::O_LICH_PHUT, 0 );
}

/** Một báo cáo đã hoàn tất, phải nộp `$phai`. */
function bc( $ma, $cs, $tu, $den, $phai, $tt = null, $dong_ds = array() ) {
	VHJP_Nguon::them( 'JP_Reports', array( 'id' => $ma, 'locationId' => $cs,
		'locationName' => 'CS ' . $cs, 'maKH' => 'KH' . $cs, 'machineType' => 'TIEN',
		'fromDate' => $tu, 'toDate' => $den, 'userId' => 'U1', 'userName' => 'Nhân Viên A',
		'revMeter' => $phai, 'totalSubmit' => $phai,
		'status' => null === $tt ? VHJP_BaoCao::TT_HOAN_TAT : $tt ) );
	$i = 0;
	foreach ( $dong_ds as $d ) {
		$i++;
		VHJP_Nguon::them( 'JP_Rows', array_merge(
			array( 'id' => $ma . '-R' . $i, 'reportId' => $ma, 'seq' => $i ), $d ) );
	}
}

/* ═════════════════════════════════════ ① MÃ LÔ SINH TỪ NỘI DUNG, KHÔNG TỪ TÊN TỆP ══════ */
nen();
bc( 'BC1', 'CS01', '2026-09-01', '2026-09-15', 500000 );
$file = array(
	array( 'date' => '2026-09-16', 'amount' => 300000, 'code' => 'FT001', 'desc' => 'CK KHBR nop tien' ),
);
$a = VHJP_DoiSoat::xem_truoc( $KT, 'CK', $file );
$b = VHJP_DoiSoat::xem_truoc( $KT, 'CK', $file );
t( 'Đọc cùng nội dung hai lần ra CÙNG mã lô', $a['batchId'] === $b['batchId'], array( $a['batchId'], $b['batchId'] ) );
t( 'Mã lô KHÔNG phụ thuộc tên tệp (tên tệp không hề là tham số)',
	1 === preg_match( '/^LO[0-9A-F]{12}$/', $a['batchId'] ), $a['batchId'] );

$file2 = $file;
$file2[0]['amount'] = 300001;
$c = VHJP_DoiSoat::xem_truoc( $KT, 'CK', $file2 );
t( '🔴 Đổi MỘT ĐỒNG trong tệp là ra lô khác', $a['batchId'] !== $c['batchId'], $c['batchId'] );

t( 'Thẻ số cộng ra đúng tổng dòng',
	$a['stat']['matched'] + $a['stat']['ambiguous'] + $a['stat']['unmatched'] === $a['stat']['rows'], $a['stat'] );
t( 'Dòng khớp đúng một cơ sở thì tự nhận', 1 === $a['stat']['matched'], $a['stat'] );

/* ═══════════════════════════════════════════════ ② MỘT LÔ CHỈ ÁP ĐƯỢC MỘT LẦN ══════════ */
$r = VHJP_DoiSoat::ap( $KT, 'CK', $file );
t( 'Áp lần đầu được', ! empty( $r['ok'] ) && 300000 === (int) $r['soTien'], $r );
$bcx = VHJP_Nguon::tim_mot( 'JP_Reports', 'id', 'BC1' );
t( 'Đã ghi 300.000 vào ô đã nhận', 300000 === (int) VHJP_Doc::num( $bcx['ktXacNhan'] ), $bcx['ktXacNhan'] );
t( 'KHÔNG đụng doanh thu', 500000 === (int) VHJP_Doc::num( $bcx['revMeter'] ), $bcx['revMeter'] );

$r2 = VHJP_DoiSoat::ap( $KT, 'CK', $file );
t( '🔴 Áp LẠI cùng một lô thì CHỐI', empty( $r2['ok'] ), $r2 );
$bcx = VHJP_Nguon::tim_mot( 'JP_Reports', 'id', 'BC1' );
t( '🔴 Và số đã nhận KHÔNG nhân đôi', 300000 === (int) VHJP_Doc::num( $bcx['ktXacNhan'] ), $bcx['ktXacNhan'] );

$xt = VHJP_DoiSoat::xem_truoc( $KT, 'CK', $file );
t( 'Xem trước nói rõ lô đã áp', ! empty( $xt['alreadyApplied'] ), $xt );

/* ══════════════════════════════════════════════ ③ KHỚP MƠ HỒ THÌ KHÔNG TỰ CHỌN ═════════ */
nen();
bc( 'BC1', 'CS01', '2026-09-01', '2026-09-15', 500000 );
bc( 'BC2', 'CS02', '2026-09-01', '2026-09-15', 500000 );
$mo = array( array( 'date' => '2026-09-16', 'amount' => 100000, 'code' => 'FT9',
	'desc' => 'CK KHBR va KHVT gop' ) );
$x = VHJP_DoiSoat::xem_truoc( $KT, 'CK', $mo );
t( '🔴 Dòng khớp HAI cơ sở KHÔNG được tự nhận', 0 === $x['stat']['matched'], $x['stat'] );
t( 'Nó nằm ở nhóm mơ hồ', 1 === $x['stat']['ambiguous'], $x['stat'] );
t( 'Và kế hoạch ghi rỗng', ! $x['plan'], $x['plan'] );

$x2 = VHJP_DoiSoat::xem_truoc( $KT, 'CK', $mo, array( 1 => 'CS02' ) );
t( 'Kế toán chỉ định thì mới khớp', 1 === $x2['stat']['matched']
	&& 'CS02' === $x2['plan'][0]['locationId'], $x2['plan'] );

/* ═══════════════════════════════════ ④ HUỶ LÔ TRỪ THEO PHÂN BỔ, KHÔNG ĐẶT VỀ 0 ═════════ */
nen();
bc( 'BC1', 'CS01', '2026-09-01', '2026-09-15', 500000 );
$f = array( array( 'date' => '2026-09-16', 'amount' => 200000, 'code' => 'FT1', 'desc' => 'CK KHBR' ) );
$ap = VHJP_DoiSoat::ap( $KT, 'CK', $f );
/* Người khác xác nhận tay thêm 100.000 SAU khi lô đã áp. */
VHJP_DoiSoat::xac_nhan_tay( $KT, 'BC1', 100000, '2026-09-17', 'thu tay tại quầy', 'ADD', 'TM' );
$bcx = VHJP_Nguon::tim_mot( 'JP_Reports', 'id', 'BC1' );
t( 'Trước khi huỷ: 200.000 + 100.000', 300000 === (int) VHJP_Doc::num( $bcx['ktXacNhan'] ), $bcx['ktXacNhan'] );

$h = VHJP_DoiSoat::huy_lo( $KT, $ap['batchId'], 'áp nhầm lô của tháng trước' );
$bcx = VHJP_Nguon::tim_mot( 'JP_Reports', 'id', 'BC1' );
t( '🔴 Huỷ lô chỉ trừ ĐÚNG 200.000, giữ nguyên phần xác nhận tay',
	100000 === (int) VHJP_Doc::num( $bcx['ktXacNhan'] ), $bcx['ktXacNhan'] );
t( 'Huỷ rồi thì lô ấy hết hiệu lực, áp lại được',
	! VHJP_DoiSoat::xem_truoc( $KT, 'CK', $f )['alreadyApplied'] );

$ls = VHJP_DoiSoat::lich_su( $KT, 30 );
t( 'Lịch sử đánh dấu lô đã huỷ', ! empty( $ls[0]['undone'] ), $ls[0] );
t( 'Và ghi lý do huỷ vào đúng ô undoneReason',
	'áp nhầm lô của tháng trước' === VHJP_Doc::str( $ls[0]['undoneReason'] ), $ls[0] );
t( 'Lô đã huỷ thì không còn nút huỷ', empty( $ls[0]['coTheHuy'] ), $ls[0] );

$hh = ne( function () use ( $KT, $ap ) { return VHJP_DoiSoat::huy_lo( $KT, $ap['batchId'], 'lại nữa' ); } );
t( 'Huỷ lô đã huỷ thì chối', ! $hh['ok'], $hh );
$hh = ne( function () use ( $KT, $ap ) { return VHJP_DoiSoat::huy_lo( $KT, $ap['batchId'], '' ); } );
t( 'Huỷ mà không ghi lý do thì chối', ! $hh['ok'], $hh );

/* ══════════════════════════════════════════════════ ⑤ CÁCH THU: RỖNG LÀ CHỐI ═══════════ */
$c = ne( function () { return VHJP_DoiSoat::cach_thu( '' ); } );
t( '🔴 Cách thu RỖNG thì chối, KHÔNG mặc định tiền mặt', ! $c['ok'], $c );
$c = ne( function () { return VHJP_DoiSoat::cach_thu( 'BANK' ); } );
t( 'Cách thu lạ cũng chối', ! $c['ok'], $c );
t( 'Ba cách hợp lệ đều nhận', 'TM' === VHJP_DoiSoat::cach_thu( 'tm' )
	&& 'CK' === VHJP_DoiSoat::cach_thu( 'CK' ) && 'QR' === VHJP_DoiSoat::cach_thu( 'qr' ) );

nen();
bc( 'BC1', 'CS01', '2026-09-01', '2026-09-15', 100000 );
$x = ne( function () use ( $KT ) {
	return VHJP_DoiSoat::xac_nhan_tay( $KT, 'BC1', 50000, '2026-09-16', 'ok', 'ADD', '' ); } );
t( 'Xác nhận tay không khai cách thu thì chối', ! $x['ok'], $x );
$x = ne( function () use ( $KT ) {
	return VHJP_DoiSoat::xac_nhan_tay( $KT, 'BC1', 50000, '2026-09-16', 'x', 'ADD', 'TM' ); } );
t( 'Lý do dưới 3 ký tự thì chối', ! $x['ok'], $x );
$x = VHJP_DoiSoat::xac_nhan_tay( $KT, 'BC1', 150000, '2026-09-16', 'khách nộp dư', 'SET', 'TM' );
t( 'Nhận VƯỢT vẫn ghi được nhưng phải báo cờ vượt', ! empty( $x['vuot'] ), $x );

/* ═════════════════════════════════════════ ⑥ BỐN CỘT CÁCH THU PHẢI CỘNG RA ĐÃ NHẬN ═════ */
nen();
bc( 'BC1', 'CS01', '2026-09-01', '2026-09-15', 300000 );
VHJP_NopTien::them( $NV, array( 'reportId' => 'BC1', 'amount' => 100000,
	'payDate' => '2026-09-16', 'method' => 'TM' ) );
VHJP_NopTien::them( $NV, array( 'reportId' => 'BC1', 'amount' => 200000,
	'payDate' => '2026-09-17', 'method' => 'CK' ) );
VHJP_NopTien::xac_nhan( $KT, 'BC1', 300000 );
$r = VHJP_Nguon::tim_mot( 'JP_Reports', 'id', 'BC1' );
$tach = VHJP_DoiSoat::tach_cach_thu( $r );
t( 'Tách theo các lần nộp thật: 100.000 TM', 100000 === (int) $tach['TM'], $tach );
t( 'và 200.000 CK', 200000 === (int) $tach['CK'], $tach );
t( '🔴 Bốn ô cộng đúng bằng đã nhận',
	300000 === (int) ( $tach['TM'] + $tach['CK'] + $tach['QR'] + $tach['KHAC'] ), $tach );
t( 'Đã khai đủ cách thu thì KHÔNG nằm trong danh sách chưa khai',
	! VHJP_DoiSoat::chua_khai_cach( $KT )['rows'] );

/* Xác nhận NHIỀU HƠN tổng đã nộp mà không khai cách thu ⇒ phần vượt là "chưa rõ". */
nen();
bc( 'BC1', 'CS01', '2026-09-01', '2026-09-15', 500000 );
VHJP_NopTien::them( $NV, array( 'reportId' => 'BC1', 'amount' => 100000,
	'payDate' => '2026-09-16', 'method' => 'TM' ) );
VHJP_NopTien::xac_nhan( $KT, 'BC1', 400000 );
$r = VHJP_Nguon::tim_mot( 'JP_Reports', 'id', 'BC1' );
$tach = VHJP_DoiSoat::tach_cach_thu( $r );
t( 'Phần vượt các lần nộp mà chưa khai thì vào "chưa rõ"', 300000 === (int) $tach['KHAC'], $tach );
$ck = VHJP_DoiSoat::chua_khai_cach( $KT );
t( 'Màn khai bù thấy đúng báo cáo ấy', 1 === count( $ck['rows'] ), $ck );
t( '🔴 Tổng của màn khai bù = đúng phần CHƯA RÕ, không phải cả khoản đã nhận',
	300000 === (int) $ck['tong'], $ck );
t( 'Hàng in ra có đủ ô giao diện đọc',
	isset( $ck['rows'][0]['coSo'], $ck['rows'][0]['kyTu'], $ck['rows'][0]['ngayNop'],
		$ck['rows'][0]['chuaKhai'] ), $ck['rows'][0] );

VHJP_DoiSoat::khai_cach( $KT, 'BC1', 'CK', 'khai bù dữ liệu cũ' );
$r = VHJP_Nguon::tim_mot( 'JP_Reports', 'id', 'BC1' );
$tach = VHJP_DoiSoat::tach_cach_thu( $r );
t( 'Khai bù xong thì "chưa rõ" về 0', 0 === (int) $tach['KHAC'], $tach );
t( 'và phần ấy chuyển sang CK', 300000 === (int) $tach['CK'], $tach );
t( 'Khai bù KHÔNG đụng số đã nhận', 400000 === (int) VHJP_Doc::num( $r['ktXacNhan'] ), $r['ktXacNhan'] );
t( 'Danh sách chưa khai rỗng đi', ! VHJP_DoiSoat::chua_khai_cach( $KT )['rows'] );

/* ═════════════════════════════════════════ ⑦ CỔNG XÁC NHẬN CỘT CHẶN LUỒNG TỰ ĐỘNG ══════ */
nen();
bc( 'BC1', 'CS01', '2026-09-01', '2026-09-15', 500000 );
VHJP_Nguon::them( 'JP_BankGD', array( 'refId' => 'FT100', 'ngay' => '2026-09-16',
	'soTien' => 200000, 'noiDung' => 'CK JPBR01 nop tien thang 9', 'trangThai' => '' ) );

$d = VHJP_DoiSoat::doi_soat_nh( $KT, true );
t( '🔴 Cổng TẮT thì gọi với ghi=true vẫn KHÔNG ghi', empty( $d['ghi'] ), $d );
$bcx = VHJP_Nguon::tim_mot( 'JP_Reports', 'id', 'BC1' );
t( '🔴 và sổ không đổi một đồng', 0 === (int) VHJP_Doc::num( $bcx['ktXacNhan'] ), $bcx['ktXacNhan'] );

VHJP_DoiSoat::xac_nhan_cot( $KT, true );
$d = VHJP_DoiSoat::doi_soat_nh( $KT, false );
t( 'Cổng BẬT mà chỉ xem trước thì cũng không ghi',
	0 === (int) VHJP_Doc::num( VHJP_Nguon::tim_mot( 'JP_Reports', 'id', 'BC1' )['ktXacNhan'] ) );
t( 'Xem trước vẫn nói sẽ ghi bao nhiêu', 200000 === (int) $d['tienTuAp'], $d );

$d = VHJP_DoiSoat::doi_soat_nh( $KT, true );
$bcx = VHJP_Nguon::tim_mot( 'JP_Reports', 'id', 'BC1' );
t( 'Cổng BẬT + ghi=true thì ghi thật', 200000 === (int) VHJP_Doc::num( $bcx['ktXacNhan'] ), $bcx['ktXacNhan'] );
$gd = VHJP_Nguon::tim_mot( 'JP_BankGD', 'refId', 'FT100' );
t( 'Giao dịch được đánh dấu ĐÃ ÁP', 'DA_AP' === VHJP_Doc::str( $gd['trangThai'] ), $gd );

$d2 = VHJP_DoiSoat::doi_soat_nh( $KT, true );
t( '🔴 Chạy lại KHÔNG ghi thêm lần nữa',
	200000 === (int) VHJP_Doc::num( VHJP_Nguon::tim_mot( 'JP_Reports', 'id', 'BC1' )['ktXacNhan'] ) );
t( 'nhưng tiền đã về vẫn được đếm vào "tiền vào NH"',
	200000 === (int) $d2['theoCoSo'][0]['tienVaoNH'], $d2['theoCoSo'][0] );

/* Dò theo MÃ ĐỊNH DANH, không dò theo tên. */
nen();
VHJP_DoiSoat::xac_nhan_cot( $KT, true );
bc( 'BC1', 'CS01', '2026-09-01', '2026-09-15', 500000 );
VHJP_Nguon::them( 'JP_BankGD', array( 'refId' => 'FT200', 'ngay' => '2026-09-16',
	'soTien' => 100000, 'noiDung' => 'CK JP Ba Ria nop tien', 'trangThai' => '' ) );
$d = VHJP_DoiSoat::doi_soat_nh( $KT, true );
t( '🔴 Luồng nền KHÔNG dò theo tên cơ sở — chỉ mã định danh', 1 === $d['soCho'], $d );
t( 'và nói rõ vì sao chưa áp',
	false !== strpos( $d['cho'][0]['lyDo'], 'KHÔNG có mã định danh' ), $d['cho'][0] );

/* Tiền về trước báo cáo ⇒ NHẬN TRƯỚC, rồi tự khớp lượt sau. */
nen();
VHJP_DoiSoat::xac_nhan_cot( $KT, true );
VHJP_Nguon::them( 'JP_BankGD', array( 'refId' => 'FT300', 'ngay' => '2026-09-16',
	'soTien' => 150000, 'noiDung' => 'CK JPBR01', 'trangThai' => '' ) );
$d = VHJP_DoiSoat::doi_soat_nh( $KT, true );
$hang = null;
foreach ( $d['theoCoSo'] as $x ) { if ( 'CS01' === $x['locationId'] ) { $hang = $x; } }
t( '🔴 Tiền về mà chưa có báo cáo thì GIỮ RIÊNG, không vứt vào hàng chờ',
	150000 === (int) $hang['nhanTruoc'] && 0 === $d['soCho'], array( $hang, $d['soCho'] ) );
$gd = VHJP_Nguon::tim_mot( 'JP_BankGD', 'refId', 'FT300' );
t( 'Đánh dấu NHAN_TRUOC kèm cơ sở', 'NHAN_TRUOC' === VHJP_Doc::str( $gd['trangThai'] )
	&& 'CS01' === VHJP_Doc::str( $gd['locationId'] ), $gd );

bc( 'BC9', 'CS01', '2026-09-01', '2026-09-15', 150000 );
$d = VHJP_DoiSoat::doi_soat_nh( $KT, true );
t( 'Duyệt báo cáo xong thì khoản nhận trước tự khớp vào',
	150000 === (int) VHJP_Doc::num( VHJP_Nguon::tim_mot( 'JP_Reports', 'id', 'BC9' )['ktXacNhan'] ) );
t( 'và lượt ấy báo đã gắn được bao nhiêu', 1 === (int) $d['khopTruoc']['gan']
	&& 150000 === (int) $d['khopTruoc']['tien'], $d['khopTruoc'] );

/* Tiền về NHIỀU HƠN phần báo cáo còn thiếu: vào được một phần, phần dư phải GIỮ LẠI.
   🔴 Đánh dấu ĐÃ ÁP cho cả giao dịch là phần dư biến mất khỏi mọi bảng — tiền có thật
      ngoài đời mà không màn nào còn nhắc tới nó. */
nen();
VHJP_DoiSoat::xac_nhan_cot( $KT, true );
bc( 'BC1', 'CS01', '2026-09-01', '2026-09-15', 150000 );
VHJP_Nguon::them( 'JP_BankGD', array( 'refId' => 'FT400', 'ngay' => '2026-09-16',
	'soTien' => 200000, 'noiDung' => 'CK JPBR01', 'trangThai' => '' ) );
$d = VHJP_DoiSoat::doi_soat_nh( $KT, true );
t( 'Vào được đúng phần còn thiếu',
	150000 === (int) VHJP_Doc::num( VHJP_Nguon::tim_mot( 'JP_Reports', 'id', 'BC1' )['ktXacNhan'] ) );
$gd = VHJP_Nguon::tim_mot( 'JP_BankGD', 'refId', 'FT400' );
t( '🔴 Vào được MỘT PHẦN thì KHÔNG đánh dấu đã áp',
	'NHAN_TRUOC' === VHJP_Doc::str( $gd['trangThai'] ), $gd );
$hang = null;
foreach ( $d['theoCoSo'] as $x ) { if ( 'CS01' === $x['locationId'] ) { $hang = $x; } }
t( '🔴 và 50.000 dư vẫn nằm ở cột nhận trước', 50000 === (int) $hang['nhanTruoc'], $hang );

/* ═════════════════════════════════════════════════════════════ ⑧ LỊCH CHẠY NỀN ═════════ */
nen();
$t1 = VHJP_DoiSoat::dat_lich( $KT, 5 );
t( 'Đặt lịch 5 phút', 5 === (int) $t1['phut'] && ! empty( $t1['co'] ), $t1 );
t( 'Cổng cột đang tắt thì lịch nói rõ là KHÔNG ghi gì',
	false !== strpos( $t1['msg'] . VHJP_DoiSoat::lich( $KT )['msg'], 'KHÔNG ghi gì' ),
	VHJP_DoiSoat::lich( $KT ) );
$t2 = VHJP_DoiSoat::dat_lich( $KT, 1 );
t( 'Mức 1 phút giao diện có bày thì máy chủ phải nhận', 1 === (int) $t2['phut'], $t2 );
$t3 = VHJP_DoiSoat::dat_lich( $KT, 0 );
t( 'Đặt 0 là tắt', 0 === (int) $t3['phut'] && empty( $t3['co'] ), $t3 );
$t4 = VHJP_DoiSoat::dat_lich( $KT, -9 );
t( 'Số âm quy về tắt, không nổ', 0 === (int) $t4['phut'], $t4 );

/* ════════════════════════════════════════════════════ ⑨ SỔ CÔNG NỢ THEO CƠ SỞ ══════════ */
nen();
/* Tháng 8: nợ 200.000 chưa trả ⇒ thành dư đầu kỳ tháng 9. */
bc( 'BC8', 'CS01', '2026-08-01', '2026-08-15', 500000 );
VHJP_NopTien::xac_nhan( $KT, 'BC8', 300000 );
bc( 'BC9', 'CS01', '2026-09-01', '2026-09-15', 400000 );
VHJP_NopTien::them( $NV, array( 'reportId' => 'BC9', 'amount' => 150000,
	'payDate' => '2026-09-16', 'method' => 'TM' ) );
VHJP_NopTien::xac_nhan( $KT, 'BC9', 150000 );
bc( 'BCX', 'CS01', '2026-09-20', '2026-09-25', 90000, VHJP_BaoCao::TT_CHO_DUYET );

$so = VHJP_So::so_cong_no( $KT, 9, 2026 );
$h  = $so['rows'][0];
t( 'Dư đầu kỳ suy từ tháng trước', 200000 === (int) $h['duDauKy'], $h );
t( '🔴 và NÓI RA suy từ đâu', false !== strpos( $h['nguonDuDauKy'], '1 báo cáo hoàn tất' ), $h );
t( 'Phát sinh chỉ đếm báo cáo HOÀN TẤT', 400000 === (int) $h['phatSinh'], $h );
t( 'Đã nhận 150.000, vào cột tiền mặt', 150000 === (int) $h['daNhan']
	&& 150000 === (int) $h['daNhanTM'], $h );
t( 'Dư cuối kỳ = đầu + phát sinh − đã nhận', 450000 === (int) $h['duCuoiKy'], $h );
t( '🔴 Báo cáo CHƯA duyệt KHÔNG vào phải thu', 400000 === (int) $h['phatSinh'], $h );
t( 'nhưng vẫn được đếm riêng', 1 === (int) $h['soBaoCaoChuaDuyet']
	&& 90000 === (int) $h['phaiThuChuaDuyet'], $h );
t( 'Bốn cột cách thu cộng ra đã nhận của cả bảng',
	(int) ( $so['tong']['daNhanTM'] + $so['tong']['daNhanCK'] + $so['tong']['daNhanQR']
		+ $so['tong']['daNhanKhac'] ) === (int) $so['tong']['daNhan'], $so['tong'] );
t( 'Tình trạng do máy chủ dựng', 'Còn thiếu' === $h['tinhTrang'], $h );

$so8 = VHJP_So::so_cong_no( $KT, 8, 2026 );
t( 'Tháng 8 không có dư đầu kỳ', 0 === (int) $so8['rows'][0]['duDauKy'], $so8['rows'][0] );
t( 'và nói thẳng là không có báo cáo nào trước đó',
	false !== strpos( $so8['rows'][0]['nguonDuDauKy'], 'Không có' ), $so8['rows'][0] );

$e = ne( function () use ( $KT ) { return VHJP_So::so_cong_no( $KT, 13, 2026 ); } );
t( 'Tháng 13 thì chối', ! $e['ok'], $e );
$e = ne( function () use ( $NV ) { return VHJP_So::so_cong_no( $NV, 9, 2026 ); } );
t( 'Nhân viên không mở được sổ công nợ', ! $e['ok'], $e );

/* ═══════════════════════════════════════════════════ ⑩ QUÉT: BÁN MÀ KHÔNG CÓ GIÁ VỐN ═══ */
nen();
bc( 'BC1', 'CS01', '2026-09-01', '2026-09-15', 500000, null, array(
	array( 'rowKind' => 'MONEY', 'itemCode' => 'H1', 'soldQty' => 4, 'price' => 10000,
		'mBefore' => 100, 'mAfter' => 150, 'machineId' => 'M1', 'amount' => 500000 ),
) );
$q = VHJP_Quet::quet( $KT, 9, 2026 );
t( '🔴 Có hàng bán mà không dòng kho nào ⇒ báo thiếu giá vốn', 1 === $q['soThieuGiaVon'], $q );
t( 'kèm số tiền đang treo', 500000 === (int) $q['tienThieuGiaVon'], $q );
t( 'và không coi là sạch', empty( $q['sach'] ), $q );

VHJP_Nguon::them( 'JP_KhoXuat', array( 'id' => 'X1', 'soChungTu' => 'BC1', 'ngay' => '2026-09-15',
	'loai' => 'BAN', 'reportId' => 'BC1', 'khoId' => 'CS01', 'locationId' => 'CS01',
	'locationName' => 'JP Ba Ria', 'itemCode' => 'H1', 'itemName' => 'Trứng',
	'qty' => 4, 'unitCost' => 6000, 'amount' => 24000, 'tkNo' => '6321', 'tkCo' => '1561' ) );
$q = VHJP_Quet::quet( $KT, 9, 2026 );
t( 'Ghi bù dòng kho thì hết báo', 0 === $q['soThieuGiaVon'], $q );

/* ⓫ dòng còn cờ thiếu lớp ⇒ giá vốn là số TẠM */
VHJP_Nguon::sua( 'JP_KhoXuat', 'X1', array( 'thieuLop' => 1 ) );
$q = VHJP_Quet::quet( $KT, 9, 2026 );
t( 'Cờ thiếu lớp bị bắt', 1 === $q['soLoThieuLop'] && 1 === $q['soDongThieuLop'], $q );
t( 'kèm giá trị đang ghi theo giá tạm', 24000 === (int) $q['tienThieuLop'], $q );
t( 'Phiếu in ra gắn với báo cáo', 'BC1' === $q['thieuLop'][0]['reportId'], $q['thieuLop'][0] );
VHJP_Nguon::sua( 'JP_KhoXuat', 'X1', array( 'thieuLop' => 0 ) );

/* ⓬ dòng kho mang tài khoản của khối khác */
VHJP_Nguon::sua( 'JP_KhoXuat', 'X1', array( 'tkNo' => '632', 'tkCo' => '1567' ) );
$q = VHJP_Quet::quet( $KT, 9, 2026 );
t( '🔴 Dòng kho mang đầu 632/1567 bị bắt', 1 === (int) $q['soDongTkCu'], $q );
t( 'kèm giá trị ghi sai đầu', 24000 === (int) $q['tienTkCu'], $q );
VHJP_Nguon::sua( 'JP_KhoXuat', 'X1', array( 'tkNo' => '6321', 'tkCo' => '1561' ) );

/* ⓭ số ở đầu báo cáo bị sửa tay */
VHJP_Nguon::sua( 'JP_Reports', 'BC1', array( 'revMeter' => 900000, 'totalSubmit' => 900000 ) );
$q = VHJP_Quet::quet( $KT, 9, 2026 );
t( '🔴 Sửa tay số ở đầu báo cáo thì tính lại ra khác', 1 === $q['soLechTinhLai'], $q );
$o = $q['lechTinhLai'][0]['o'][0];
t( 'nói rõ ô nào, đang lưu bao nhiêu, tính lại bao nhiêu',
	isset( $o['ten'], $o['luu'], $o['lai'] ) && 900000 === (int) $o['luu'], $o );
t( 'Quét KHÔNG tự ghi đè số đã ký',
	900000 === (int) VHJP_Doc::num( VHJP_Nguon::tim_mot( 'JP_Reports', 'id', 'BC1' )['revMeter'] ) );

/* ═══════════════════════════════════════════════════════════ ⑭ HAI KỲ CHỒNG NHAU ═══════ */
nen();
/* Chồng NGÀY nhưng chỉ số NỐI TIẾP ⇒ sai nhãn kỳ, KHÔNG mất tiền. */
bc( 'BCA', 'CS01', '2026-09-01', '2026-09-20', 100000, null, array(
	array( 'rowKind' => 'MONEY', 'machineId' => 'M1', 'mBefore' => 100, 'mAfter' => 200,
		'price' => 1000, 'amount' => 100000 ),
) );
bc( 'BCB', 'CS01', '2026-09-10', '2026-09-30', 100000, null, array(
	array( 'rowKind' => 'MONEY', 'machineId' => 'M1', 'mBefore' => 200, 'mAfter' => 300,
		'price' => 1000, 'amount' => 100000 ),
) );
$q = VHJP_Quet::quet( $KT, 9, 2026 );
t( 'Chồng ngày thì bị bắt', 1 === $q['soKyChong'], $q );
t( '🔴 nhưng chỉ số NỐI TIẾP thì KHÔNG báo tiền tính hai lần',
	0 === $q['soKyChongTien'] && 0 === (int) $q['tienKyChong'], $q );
/* 🔴 Kỳ trước chốt ở 200, kỳ sau mở ở 200 là NỐI TIẾP. Đo bằng khoảng ĐÓNG thì ô ấy bị đếm
   là "trùng chỉ số" — số tiền vẫn ra 0 nên bảng trông vẫn đúng, nhưng cột "ô máy trùng chỉ
   số" báo 1, và kế toán đi tìm một khoản chồng không tồn tại. */
t( '🔴 và ô máy nối tiếp KHÔNG bị đếm là trùng chỉ số',
	0 === (int) $q['kyChong'][0]['soOTrung'], $q['kyChong'][0] );
t( 'Cặp in ra đủ hai báo cáo và khoảng chồng',
	'BCA' === $q['kyChong'][0]['a']['id'] && 'BCB' === $q['kyChong'][0]['b']['id']
	&& '' !== $q['kyChong'][0]['chongTu'], $q['kyChong'][0] );

/* Chồng CHỈ SỐ ⇒ tiền vào sổ hai lượt. */
VHJP_Nguon::sua( 'JP_Rows', 'BCB-R1', array( 'mBefore' => 150, 'mAfter' => 300 ) );
$q = VHJP_Quet::quet( $KT, 9, 2026 );
t( '🔴 Chồng CHỈ SỐ thì báo tiền bị tính hai lần', 1 === $q['soKyChongTien'], $q );
t( 'đúng 50 vạch × 1.000đ', 50000 === (int) $q['tienKyChong'], $q );
t( 'và đếm được ô máy nào trùng', 1 === (int) $q['kyChong'][0]['soOTrung'], $q['kyChong'][0] );

/* Hai cơ sở khác nhau thì không phải chồng. */
VHJP_Nguon::sua( 'JP_Reports', 'BCB', array( 'locationId' => 'CS02' ) );
$q = VHJP_Quet::quet( $KT, 9, 2026 );
t( 'Khác cơ sở thì KHÔNG phải kỳ chồng nhau', 0 === $q['soKyChong'], $q );

/* ═══════════════════════════════════════════════════════════════════ ⑮ CỔNG ════════════ */
nen();
bc( 'BC1', 'CS01', '2026-09-01', '2026-09-15', 100000 );
$ra = VHJP_Cong::so_cong_no( array( 'THE', 9, 2026 ), $KT );
t( 'Cổng: so_cong_no đọc tháng/năm ở $args[1..2]', 9 === $ra['thang'] && 2026 === $ra['nam'], $ra );
$ra = VHJP_Cong::quet_dc( array( 'THE', 9, 2026 ), $KT );
t( 'Cổng: quet_dc đọc tháng/năm ở $args[1..2]', 9 === $ra['thang'], $ra );
$ra = VHJP_Cong::ds_chua_khai( array( 'THE' ), $KT );
t( 'Cổng: ds_chua_khai không cần tham số nghiệp vụ', ! empty( $ra['ok'] ), $ra );
$ra = VHJP_Cong::ds_lich( array( 'THE' ), $KT );
t( 'Cổng: ds_lich', isset( $ra['phut'] ), $ra );
$ra = VHJP_Cong::ds_xac_nhan_cot( array( 'THE', true ), $KT );
t( 'Cổng: ds_xac_nhan_cot đọc cờ ở $args[1]', ! empty( $ra['daXacNhan'] ), $ra );
$ra = VHJP_Cong::ds_doi_soat( array( 'THE', false ), $KT );
t( 'Cổng: ds_doi_soat', isset( $ra['theoCoSo'] ), $ra );
$ra = VHJP_Cong::ds_doc_gd( array( 'THE', 5 ), $KT );
t( 'Cổng: ds_doc_gd trả đủ ô màn kiểm cột đọc',
	isset( $ra['nguon'], $ra['tieuDe'], $ra['mau'], $ra['rows'], $ra['soDong'] ), $ra );

/* Mọi hàm đối soát đều là việc của kế toán. */
foreach ( array( 'lich_su', 'chua_khai_cach', 'lich' ) as $fn ) {
	$e = ne( function () use ( $fn, $NV ) { return VHJP_DoiSoat::$fn( $NV ); } );
	t( 'Nhân viên gọi ' . $fn . ' thì chối', ! $e['ok'], $e );
}
$e = ne( function () use ( $NV ) { return VHJP_Quet::quet( $NV, 9, 2026 ); } );
t( 'Nhân viên gọi quét dây chuyền thì chối', ! $e['ok'], $e );
/* 🔴 Chối ở CỬA CỦA CHÍNH NÓ, không nhờ cửa của `VHJP_ButToan`. Quét gọi `doi_tk_kho_cu()`
   nên hiện thời vẫn chối được kể cả khi bỏ chốt ở đây — nhưng đó là may, không phải thiết
   kế: đổi thứ tự sáu phép hay bỏ phép ④ đi là nhân viên đọc được toàn bộ sổ. So THẲNG câu
   lỗi để bài kiểm biết cửa nào vừa chối. */
t( '🔴 và chối ngay ở cửa của chính màn quét',
	false !== strpos( (string) $e['loi'], 'Việc này cần tài khoản kế toán' ), $e );

/* ------------------------------------------------------------------ in kết quả */
echo "\n";
if ( $TRUOT ) {
	echo 'ĐỎ — ' . count( $TRUOT ) . " phép trượt:\n";
	foreach ( $TRUOT as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "\nĐạt {$DAT} · trượt " . count( $TRUOT ) . "\n";
	exit( 1 );
}
echo "XANH — {$DAT} phép đều đạt.\n";
exit( 0 );
