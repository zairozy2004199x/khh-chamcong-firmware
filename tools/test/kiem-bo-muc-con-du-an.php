<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * CHI PHÍ DỰ ÁN CHỈ CÒN HẠNG MỤC LỚN — CHỐT Ở MÁY CHỦ, KHÔNG Ở NÚT BẤM.
 *
 * Anh Thắng 17/09/2026: *"Đối với chi phí dự án, hạng mục con bỏ đi, vì các chi phí đều là hạng
 * mục lớn"*, và chốt tiếp: **giữ nguyên mục con ĐANG CÓ, chỉ cấm tạo mới** — không một con số
 * nào của dự án cũ được phép đổi.
 *
 * =============================================================================================
 * 🔴 VÌ SAO KHÔNG ĐỦ KHI CHỈ GỠ NÚT. `saveDuAnLine` là một cửa API công khai. Một bản app.html
 *    cũ còn nằm trong bộ nhớ đệm trình duyệt của ai đó vẫn gửi `capCha` lên như thường, và mục
 *    con lại mọc — mọc IM LẶNG, chỉ lộ ra ở chỗ tiền của hạng mục cha bỗng chuyển sang tính
 *    bằng "tổng con" (`tien_hm()`), tức một con số khác hẳn mà không ai đụng vào nó.
 *
 * 🔴 VÀ VÌ SAO PHẢI CHO SỬA DÒNG CON CŨ. Chặn cứng mọi `capCha` thì mở một mục con cũ ra sửa
 *    mỗi cái ghi chú cũng bị chối. Người ta sẽ đi xoá dòng rồi nhập lại — mất ảnh bill, mất hồ
 *    sơ đính kèm, mất loại chi phí và mã tài khoản đã gắn. Chốt phải phân biệt "gán cha MỚI"
 *    với "giữ nguyên cha đang có".
 *
 * Chạy: php tools/test/kiem-bo-muc-con-du-an.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : var_export( $them, true ) ) ) : '' );
}
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . var_export( $mong, true ) . ')', $mong === $thuc, $thuc ); }

VHCP_Auth::dat_vai_tro( 'Admin', 'KT' );
$ma = VHCP_DuAn::create_du_an( 'Setup lắp đặt', 'Gian bỏ mục con', 'NV' )['maDA'];
VHCP_DuAn::add_line( $ma, array( 'noiDung' => 'Mua đồ điện', 'duToan' => 5000000 ) );

/* ═══ 1. THÊM DÒNG: CHỐI CHA MỚI ═══════════════════════════════════════════════════════ */
$r = VHCP_DuAn::add_line( $ma, array( 'noiDung' => 'Bóng đèn', 'capCha' => 'Mua đồ điện', 'thucTe' => 2000000 ) );
t( '🔴 thêm một MỤC CON mới → CHỐI', empty( $r['success'] ), $r );
t( '   và câu chối nói ra phải làm gì thay thế',
	isset( $r['error'] ) && false !== mb_strpos( (string) $r['error'], 'hạng mục lớn' ), $r );
t( '   nhắc luôn đường "Phát sinh" cho khoản nảy ra lúc thi công',
	isset( $r['error'] ) && false !== mb_strpos( (string) $r['error'], 'Phát sinh' ), $r );

$d = VHCP_DuAn::get_du_an( $ma );
teq( '🔴 và KHÔNG có dòng nào lọt vào sổ', 1, count( $d['lines'] ) );

/* ⚠️ `(Phát sinh)` KHÔNG phải mục con — nó đứng độc lập, và anh Thắng không bảo bỏ nó. */
$r = VHCP_DuAn::add_line( $ma, array( 'noiDung' => 'Phát sinh đêm', 'capCha' => '(Phát sinh)', 'thucTe' => 300000 ) );
t( '🔴 "(Phát sinh)" vẫn thêm được — nó không phải mục con', ! empty( $r['success'] ), $r );
$r = VHCP_DuAn::add_line( $ma, array( 'noiDung' => 'Thợ phụ', 'duToan' => 1000000 ) );
t( '   hạng mục lớn vẫn thêm được như thường', ! empty( $r['success'] ), $r );

/* ═══ 2. SỬA DÒNG: CHỐI CHA MỚI, NHƯNG GIỮ NGUYÊN CHA CŨ THÌ CHO QUA ═══════════════════ */
/* Dựng một mục con CŨ đúng như sổ đang chạy — qua cửa di trú, không qua cửa nghiệp vụ. */
VHCP_DuAn::them_dong_muc_con_cu( $ma, array( 'noiDung' => 'Dây điện', 'capCha' => 'Mua đồ điện',
	'thucTe' => 700000, 'note' => 'cũ' ) );
$d   = VHCP_DuAn::get_du_an( $ma );
$row = 0; $rowLon = 0;
foreach ( $d['lines'] as $l ) {
	if ( 'Dây điện' === $l['noiDung'] ) { $row = (int) $l['row']; }
	if ( 'Thợ phụ' === $l['noiDung'] ) { $rowLon = (int) $l['row']; }
}
t( 'dựng được một mục con cũ để thử', $row > 0, $d['lines'] );

$r = VHCP_DuAn::update_line( $ma, $row, array( 'noiDung' => 'Dây điện', 'capCha' => 'Mua đồ điện',
	'thucTe' => 700000, 'note' => 'sửa ghi chú' ) );
t( '🔴 sửa một MỤC CON CŨ mà giữ nguyên cha → CHO QUA (không thì người ta đi xoá dòng)',
	! empty( $r['success'] ), $r );
$d2 = VHCP_DuAn::get_du_an( $ma );
foreach ( $d2['lines'] as $l ) {
	if ( (int) $l['row'] === $row ) {
		teq( '   và nó vẫn là mục con của đúng hạng mục cũ', 'Mua đồ điện', (string) $l['capCha'] );
		teq( '   ghi chú đã sửa thật', 'sửa ghi chú', (string) $l['note'] );
	}
}

/* 🔴 ĐƯA LÊN thành hạng mục lớn thì luôn được — đó là lối ra của dữ liệu cũ. */
$r = VHCP_DuAn::update_line( $ma, $row, array( 'noiDung' => 'Dây điện', 'capCha' => '', 'thucTe' => 700000 ) );
t( '🔴 đưa mục con cũ LÊN hạng mục lớn → cho qua', ! empty( $r['success'] ), $r );

/* 🔴 Và chiều ngược lại thì chối: hạng mục lớn KHÔNG hạ xuống làm con được nữa. */
$r = VHCP_DuAn::update_line( $ma, $rowLon, array( 'noiDung' => 'Thợ phụ', 'capCha' => 'Mua đồ điện', 'thucTe' => 0 ) );
t( '🔴 hạ một HẠNG MỤC LỚN xuống làm mục con → CHỐI', empty( $r['success'] ), $r );
$d3 = VHCP_DuAn::get_du_an( $ma );
foreach ( $d3['lines'] as $l ) {
	if ( (int) $l['row'] === $rowLon ) {
		teq( '   và nó vẫn đứng nguyên là hạng mục lớn', '', (string) $l['capCha'] );
	}
}

/* ═══ 3. DỮ LIỆU CŨ TÍNH ĐÚNG Y NHƯ TRƯỚC — lời hứa "không đổi con số nào" ═════════════ */
$maC = VHCP_DuAn::create_du_an( 'Setup lắp đặt', 'Gian dữ liệu cũ', 'NV' )['maDA'];
VHCP_DuAn::add_line( $maC, array( 'noiDung' => 'Vật tư', 'duToan' => 9000000, 'thucTe' => 500000 ) );
foreach ( array( array( 'Ốc vít', 2000000 ), array( 'Keo', 3000000 ) ) as $c ) {
	VHCP_DuAn::them_dong_muc_con_cu( $maC, array( 'noiDung' => $c[0], 'capCha' => 'Vật tư', 'thucTe' => $c[1] ) );
}
$dC = VHCP_DuAn::get_du_an( $maC );
$rV = 0;
foreach ( $dC['lines'] as $l ) { if ( 'Vật tư' === $l['noiDung'] && '' === $l['capCha'] ) { $rV = (int) $l['row']; } }
teq( '🔴 hạng mục cha CÓ CON: tiền vẫn = tổng con (5tr), KHÔNG cộng 500k của chính nó',
	5000000, (int) VHCP_DuAn::tien_hm( $maC, $rV ) );
teq( '   và tổng thực tế cả dự án vẫn đúng 5tr', 5000000, (int) $dC['tongThucTe'] );
teq( '   ba dòng cũ còn đủ trên bảng', 3, count( $dC['lines'] ) );

/* ══════════════════════════════════════════════════════════════════════════════════════════
 * 3b. 🔴 CHỐT HOÀN THÀNH PHẢI CÓ SỐ TIỀN THẬT
 *
 * 'xong' nghĩa là "đây là chi thực tế". Bản trước chỉ đòi có hoá đơn, không đòi có SỐ TIỀN —
 * nên chốt được một hạng mục 48 triệu với thực tế 0đ, rồi đem đi quyết toán: lệnh quyết toán
 * ra 0đ (`xin_quyet_toan_dot` cộng bằng `tien_hm()`), khoản tạm ứng vẫn treo nguyên trên TK
 * 141, mà sổ thì trông như đã tất toán. Cùng họ với lỗi lệnh xin 0đ, chỉ khác đầu kia của chuỗi.
 * ══════════════════════════════════════════════════════════════════════════════════════════ */
VHCP_Auth::dat_vai_tro( 'Admin', 'KT' );
$maX = VHCP_DuAn::create_du_an( 'Setup lắp đặt', 'Gian chốt 0đ', 'NV' )['maDA'];
VHCP_DuAn::add_line( $maX, array( 'noiDung' => 'Thợ Phụ', 'duToan' => 48000000 ) );
VHCP_DuAn::add_line( $maX, array( 'noiDung' => 'Xe cẩu', 'duToan' => 1000000, 'thucTe' => 900000 ) );
$dX = VHCP_DuAn::get_du_an( $maX );
$RX = array();
foreach ( $dX['lines'] as $l ) { if ( '' === $l['capCha'] ) { $RX[ $l['noiDung'] ] = (int) $l['row']; } }

$r = VHCP_DuAn::dat_hm( $maX, $RX['Thợ Phụ'], 'xong', array( 'hoaDon' => 'hd.pdf' ) );
t( '🔴 chốt hoàn thành khi thực tế = 0 → CHỐI, dù đã có hoá đơn', empty( $r['success'] ), $r );
t( '   và câu chối nói ra hậu quả (quyết toán ra 0đ, tạm ứng vẫn treo)',
	isset( $r['error'] ) && false !== mb_strpos( (string) $r['error'], '0đ' ), $r );
t( '   chỉ đúng ô phải điền', isset( $r['error'] )
	&& false !== mb_strpos( (string) $r['error'], 'Chi phí thực tế' ), $r );
teq( '   trạng thái không đổi', 'nhap', VHCP_DuAn::hm_cua( $maX, $RX['Thợ Phụ'] )['tt'] );

$r = VHCP_DuAn::dat_hm( $maX, $RX['Xe cẩu'], 'xong', array( 'hoaDon' => 'hd2.pdf' ) );
t( '🔴 có số tiền thật thì chốt được như thường', ! empty( $r['success'] ), $r );

/* 🔴 ĐƠN CƠ SỞ: tiền ở SỐ LƯỢNG × ĐƠN GIÁ, ô thực tế để trống — máy chủ phải tự lấy thành
   tiền, không thì chốt của nó bị chặn oan VÀ quyết toán của nó ra 0đ. Luật này trước chỉ nằm
   ở màn web (`app.html`: "thực tế trống -> = thành tiền"), tức mọi đường ghi khác đều hụt. */
$maY = VHCP_DuAn::tao_don_coso( 'Tuần thử thực tế', 'Sếp', '14/09/2026', '20/09/2026' )['maDA'];
VHCP_DuAn::add_line( $maY, array( 'noiDung' => 'Cáp màn hình', 'soLuong' => 2, 'donGia' => 500000 ) );
$dY = VHCP_DuAn::get_du_an( $maY );
$rY = 0;
foreach ( $dY['lines'] as $l ) { if ( 'Cáp màn hình' === $l['noiDung'] ) { $rY = (int) $l['row']; } }
teq( '🔴 thực tế để trống → máy chủ lấy SL × đơn giá (1tr), không phải 0',
	1000000, (int) VHCP_DuAn::tien_hm( $maY, $rY ) );
$r = VHCP_DuAn::dat_hm( $maY, $rY, 'xong', array( 'hoaDon' => 'hd3.pdf' ) );
t( '   nên chốt hoàn thành không bị chặn oan', ! empty( $r['success'] ), $r );

/* ⚠️ Gửi THẲNG số 0 là lời khai có chủ ý ("hàng được tặng") — giữ nguyên 0, đừng đè bằng
   thành tiền, không thì tự sinh ra một khoản chi không có thật. */
$maZ = VHCP_DuAn::tao_don_coso( 'Tuần hàng tặng', 'Sếp', '14/09/2026', '20/09/2026' )['maDA'];
VHCP_DuAn::add_line( $maZ, array( 'noiDung' => 'Hàng tặng', 'soLuong' => 1, 'donGia' => 500000, 'thucTe' => 0 ) );
$dZ = VHCP_DuAn::get_du_an( $maZ );
foreach ( $dZ['lines'] as $l ) {
	if ( 'Hàng tặng' === $l['noiDung'] ) {
		teq( '🔴 khai thẳng 0 thì GIỮ 0, không đè bằng thành tiền', 0.0, (float) $l['thucTe'] );
	}
}

/* ═══ 4. CỬA DI TRÚ KHÔNG ĐƯỢC LỘ RA API ══════════════════════════════════════════════ */
$src = file_get_contents( $goc . '/wordpress/vhcp-chi-phi/includes/class-vhcp-api.php' );
t( '🔴 `them_dong_muc_con_cu` KHÔNG có mặt trong bảng cửa API — nó là cửa di trú, không phải '
	. 'cửa nghiệp vụ', false === strpos( $src, 'them_dong_muc_con_cu' ) );

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if ( $TRUOT ) {
	echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n";
	foreach ( $TRUOT as $x ) { echo '  · ' . $x . "\n"; }
	exit( 1 );
}
echo "\n✓ SẠCH — $DAT phép: hết đường tạo mục con, mục con cũ còn nguyên và tính đúng như trước.\n";
