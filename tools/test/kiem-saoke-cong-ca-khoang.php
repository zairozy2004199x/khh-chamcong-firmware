<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * SAO KÊ 0.55.0 — BẢNG "TỪ CỔNG" ĐỌC CẢ KHOẢNG: lọc cơ sở ở máy chủ TRƯỚC khi cắt 2.000 dòng; tổng theo tài khoản / cơ sở
 * tính trên mọi dòng; nói thật khi bị cắt.
 *
 * Anh Thắng 25/09/2026: *"bên ghế và sao kê đang đọc khác nhau"* — Sao Kê: GALAXY QUANG TRUNG 5 dòng / 130.000đ ("5/2000
 * dòng"); Ghế (đọc kho đủ khoảng): 4.950.000đ. Màn Sao Kê cắt 5.000 dòng mới nhất rồi mới lọc ở trình duyệt.
 * Chạy: php tools/test/kiem-saoke-cong-ca-khoang.php
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
require_once __DIR__ . '/lib/be-saoke.php';
require_once $GOC . '/vhcp-saoke/vhcp-saoke.php';
t( 'nạp được lớp SAOKE_App', class_exists( 'SAOKE_App' ) );
$db = $GLOBALS['wpdb']; $GLOBALS['OPT']['saoke_pin'] = '1234';
function dong( $id, $ngay, $tien, $may, $tk = '111', $docDuoc = 1, $huong = 'Đến' ) {
	return array( 'id' => $id, 'nguon' => 'vietqr', 'khoa' => 'vietqr|K' . $id, 'ma_gd' => 'K' . $id, 'ref' => 'R' . $id, 'thoi_diem' => $ngay, 'so_tien' => $tien, 'huong' => $huong, 'trang_thai' => 'Thành công',
		'so_tk' => $tk, 'noi_dung' => 'VQR' . $id . ' ' . $may, 'diem_ban' => '', 'ma_ch' => '', 'may_tay' => '', 'doc_duoc' => $docDuoc, 'raw' => '{"id":' . $id . '}', 'nhan_luc' => $ngay );
}
/* Không có Ghế trong bài này → nhãn cơ sở = "⚠ <cơ sở suy từ tên máy> (chưa quy được cơ sở Ghế)". Bài chỉ cần nhãn ỔN ĐỊNH. */
$db->hang = array(
	dong( 1, '2026-09-01 10:00:00', 100000, 'GLX QT 01' ), dong( 2, '2026-09-02 10:00:00', 200000, 'GLX QT 02' ), dong( 3, '2026-09-25 10:00:00', 50000, 'GLX QT 01', '222' ),
	dong( 4, '2026-09-10 10:00:00', 70000, 'AMTP 12' ), dong( 5, '2026-09-11 10:00:00', 30000, 'AMTP 13', '222' ),
	dong( 6, '2026-09-12 10:00:00', 999999, 'AMTP 12', '111', 0 ),            // chưa đọc được → không tính tiền
	dong( 7, '2026-09-12 11:00:00', 5000, 'AMTP 12', '111', 1, 'Đi' ),        // tiền đi → bỏ
);
echo "── 1. Không lọc: tổng theo cơ sở & tài khoản trên CẢ khoảng ─────\n";
$r = SAOKE_App::rpc_getSaoKeCong( array( '1234', 'vietqr', '01/09/2026', '25/09/2026', '' ) );
t( 'ok, 5 dòng đọc được/đến hiện ra', ! empty( $r['ok'] ) && 5 === count( $r['cong'] ), isset( $r['cong'] ) ? count( $r['cong'] ) : $r );
$cs = array(); foreach ( $r['theoCoSo'] as $x ) { $cs[ $x['ten'] ] = $x; }
$nGlx = ''; $nAm = ''; foreach ( array_keys( $cs ) as $k ) { if ( false !== stripos( $k, 'GLX QT' ) ) { $nGlx = $k; } if ( false !== stripos( $k, 'AMTP' ) ) { $nAm = $k; } }
t( '🔴 theoCoSo: GLX 3 GD / 350.000đ · AMTP 2 GD / 100.000đ, xếp tiền giảm dần', '' !== $nGlx && '' !== $nAm && 3 === $cs[ $nGlx ]['dong'] && 350000 === $cs[ $nGlx ]['tien'] && 2 === $cs[ $nAm ]['dong'] && 100000 === $cs[ $nAm ]['tien'] && $r['theoCoSo'][0]['ten'] === $nGlx, $r['theoCoSo'] );
$tk = array(); foreach ( $r['taiKhoan'] as $x ) { $tk[ $x['soTK'] ] = $x; }
t( '🔴 taiKhoan (GROUP BY cả khoảng): 111 → 370.000đ / 3 dòng; 222 → 80.000đ / 2 dòng (bỏ dòng chưa đọc & tiền đi)', 370000 === $tk['111']['tien'] && 3 === $tk['111']['dong'] && 80000 === $tk['222']['tien'] && 2 === $tk['222']['dong'], $r['taiKhoan'] );
t( 'không bị cắt: biCat false, congDongKhoang 5 = số dòng hiện, congTienKhoang 450.000', empty( $r['biCat'] ) && 5 === $r['congDongKhoang'] && 450000 === $r['congTienKhoang'] && 450000 === $r['congTien'] );
t( 'payloadCuoi = raw của dòng mới nhất (câu riêng), dòng đọc được không mang raw', '{"id":7}' === $r['payloadCuoi'] );
echo "── 2. Lọc cơ sở ở máy chủ ──────────────────────────────────────\n";
$r2 = SAOKE_App::rpc_getSaoKeCong( array( '1234', 'vietqr', '01/09/2026', '25/09/2026', '', $nGlx ) );
t( '🔴 chọn GLX → bảng chỉ 3 dòng GLX, tổng 350.000 (không phải 5 dòng rồi lọc ở trình duyệt)', 3 === count( $r2['cong'] ) && 350000 === $r2['congTien'] && 3 === $r2['congDongKhoang'] && $nGlx === $r2['locCoSo'], array( count( $r2['cong'] ), $r2['congTien'] ) );
t( 'theoCoSo vẫn kể ĐỦ mọi cơ sở (ô xổ không co lại theo lựa chọn)', 2 === count( $r2['theoCoSo'] ) );
t( 'taiKhoan vẫn kể đủ hai tài khoản', 2 === count( $r2['taiKhoan'] ) );
echo "── 2b. Lọc NGÀY ở máy chủ (0.56.0) ─────────────────────────────\n";
$ng = array(); foreach ( $r2['theoNgay'] as $x ) { $ng[ $x['ngay'] ] = $x; }
t( '🔴 theoNgay kể đủ mọi ngày có GLX trong khoảng (01, 02, 25/09), mới nhất trước, kèm GD · tiền', 3 === count( $r2['theoNgay'] ) && '25/09/2026' === $r2['theoNgay'][0]['ngay'] && 1 === $ng['01/09/2026']['dong'] && 100000 === $ng['01/09/2026']['tien'], $r2['theoNgay'] );
$r2b = SAOKE_App::rpc_getSaoKeCong( array( '1234', 'vietqr', '01/09/2026', '25/09/2026', '', $nGlx, '02/09/2026' ) );
t( '🔴 chọn ngày 02/09 → chỉ dòng GLX của ngày ấy (1 dòng / 200.000), theoNgay vẫn đủ 3 ngày, locNgay trả về', 1 === count( $r2b['cong'] ) && 200000 === $r2b['congTien'] && 1 === $r2b['congDongKhoang'] && 3 === count( $r2b['theoNgay'] ) && '02/09/2026' === $r2b['locNgay'], array( count( $r2b['cong'] ), $r2b['congTien'] ) );
t( 'ngày sai định dạng → bỏ qua lọc ngày', 3 === count( SAOKE_App::rpc_getSaoKeCong( array( '1234', 'vietqr', '01/09/2026', '25/09/2026', '', $nGlx, '2026-09-02' ) )['cong'] ) );
echo "── 3. Lọc tài khoản trong SQL, tổng tài khoản vẫn đủ ──────────\n";
$r3 = SAOKE_App::rpc_getSaoKeCong( array( '1234', 'vietqr', '01/09/2026', '25/09/2026', '222' ) );
t( '🔴 chỉ dòng của 222: 2 dòng / 80.000; ô xổ vẫn có cả 111', 2 === count( $r3['cong'] ) && 80000 === $r3['congTien'] && isset( $tk['111'] ) && 2 === count( $r3['taiKhoan'] ), array( count( $r3['cong'] ), $r3['congTien'] ) );
echo "── 4. Bị cắt 2.000 dòng → nói thật, tổng vẫn cả khoảng ─────────\n";
for ( $i = 100; $i < 2150; $i++ ) { $db->hang[] = dong( $i, '2026-09-' . str_pad( 1 + ( $i % 20 ), 2, '0', STR_PAD_LEFT ) . ' 12:00:00', 1000, 'AMTP 12' ); }
$r4 = SAOKE_App::rpc_getSaoKeCong( array( '1234', 'vietqr', '01/09/2026', '25/09/2026', '' ) );
t( '🔴 hiện đúng 2.000 dòng, congDongKhoang 2.055, biCat true, congTienKhoang = tổng MỌI dòng (450.000 + 2.050.000)', 2000 === count( $r4['cong'] ) && 2055 === $r4['congDongKhoang'] && ! empty( $r4['biCat'] ) && 2500000 === $r4['congTienKhoang'], array( count( $r4['cong'] ), $r4['congDongKhoang'], $r4['congTienKhoang'] ) );
t( 'congTuNgay = thời điểm dòng cũ nhất ĐANG HIỆN (dd/mm/yyyy …)', preg_match( '#^\d{2}/\d{2}/2026#', (string) $r4['congTuNgay'] ) === 1, $r4['congTuNgay'] );
$cs4 = array(); foreach ( $r4['theoCoSo'] as $x ) { $cs4[ $x['ten'] ] = $x; }
t( 'theoCoSo AMTP cộng đủ 2.052 GD dù bảng cắt', 2052 === $cs4[ $nAm ]['dong'] && 2150000 === $cs4[ $nAm ]['tien'], $cs4[ $nAm ] );
echo "── 4b. Duyệt theo TRANG (0.57.0) — trang 500 dòng, kết quả y như một trang ───\n";
SAOKE_App::$trang_cong = 500;
$r5 = SAOKE_App::rpc_getSaoKeCong( array( '1234', 'vietqr', '01/09/2026', '25/09/2026', '' ) );
t( '🔴 2.055 dòng qua 5 trang (keyset thoi_diem,id): hiện 2.000, congDongKhoang 2.055, tổng 2.500.000 — không còn trần 60.000', 2000 === count( $r5['cong'] ) && 2055 === $r5['congDongKhoang'] && 2500000 === $r5['congTienKhoang'] && empty( $r5['quaNhieu'] ), array( count( $r5['cong'] ), $r5['congDongKhoang'], $r5['congTienKhoang'] ) );
t( 'theoCoSo / taiKhoan y như một trang', 2052 === (function ( $r ) { foreach ( $r['theoCoSo'] as $x ) { if ( false !== stripos( $x['ten'], 'AMTP' ) ) { return $x['dong']; } } return 0; })( $r5 ) && 2 === count( $r5['taiKhoan'] ) );
SAOKE_App::$trang_cong = 20000;
echo "── 5. Màn hình ─────────────────────────────────────────────────\n";
$ap = file_get_contents( $GOC . '/vhcp-saoke/app.html' );
t( 'gửi cơ sở làm tham số 6, ngày tham số 7; ô xổ dựng từ d.theoCoSo / d.theoNgay; đổi cơ sở hay ngày → tải lại từ máy chủ', false !== strpos( $ap, ".getSaoKeCong(PIN, nguon, tu, den, tk, CG_LOCCS[nguon] || '', CG_LOCNGAY[nguon] || '')" ) && false !== strpos( $ap, 'if(selN && d.theoNgay){' ) && false !== strpos( $ap, "CG_LOCNGAY[nguon] = selN.value || ''; taiSaoKeCong(nguon); return;" ) && false !== strpos( $ap, 'if(sel && d.theoCoSo){' ) && false !== strpos( $ap, "CG_LOCCS[nguon] = sel.value || ''; taiSaoKeCong(nguon); return;" ) );
t( 'nhãn nói thật khi bị cắt + tổng cả khoảng; thẻ "Từ cổng" lấy congTienKhoang', false !== strpos( $ap, 'đang hiện \' + (d.cong||[]).length + \' dòng mới nhất (từ' ) && false !== strpos( $ap, 'fmt(d.congTienKhoang != null ? d.congTienKhoang : d.congTien)' ) );
ket_luan( 'bảng Từ cổng đọc cả khoảng: lọc cơ sở ở máy chủ, tổng không còn là tổng của 2.000 dòng đang hiện.' );
