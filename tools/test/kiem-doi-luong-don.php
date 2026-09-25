<?php
/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * ĐỔI LUỒNG CHO ĐƠN ĐANG CHẠY — `VHCP_Don::doi_luong_don()` + hai chốt gốc rễ.
 *
 * Anh Thắng 25/09/2026 (ảnh đơn FUNFEST PHÚ QUỐC · T10/2026 · "Chờ quyết toán" · lịch sử "Gửi quyết
 * toán (trực tiếp) — 11 hạng mục" · Tạm ứng xin 80.000.000đ · hộp luồng khoá "Qua tạm ứng — theo vai
 * trò, không chọn ở đây"): *"Đơn này sai luôn, anh muốn chỉnh cho đơn đó lại luồng khác được không"*.
 *
 * Chốt: kế toán đổi được luồng khi tiền CHƯA ra két (trực tiếp ở "Chờ quyết toán" = chưa cấp đồng
 * nào → đổi được); đổi xong đơn về Nháp, hạng mục + tạm ứng xin GIỮ, số đã duyệt (nếu có) gỡ; ghi vết.
 * Gốc rễ: đơn TRỰC TIẾP không nhận tạm ứng xin, và có số xin thì không gửi quyết toán thẳng được.
 * Chạy: php tools/test/kiem-doi-luong-don.php
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );
vhcp_test_dat_gio( '2026-09-25 15:30:00' );
VHCP_Auth::dat_vai_tro( 'Admin', 'Anh Thắng' );

$DAT = 0; $TRUOT = array();
function t( $ten, $ok, $them = null ) {
	global $DAT, $TRUOT;
	if ( $ok ) { $DAT++; return; }
	$TRUOT[] = $ten . ( null !== $them ? ( "\n      → " . ( is_scalar( $them ) ? $them : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) ) : '' );
}
function teq( $ten, $mong, $thuc ) { t( $ten . ' (mong ' . json_encode( $mong, JSON_UNESCAPED_UNICODE ) . ')', $mong === $thuc, $thuc ); }
function _tt( $ma ) { $d = VHCP_Don::don_row( $ma ); return $d ? (string) $d['trang_thai'] : '(không có)'; }
function _lg( $ma ) { return VHCP_Don::luong_don( VHCP_Don::don_row( $ma ) ); }

VHCP_Cfg::save_config( array(
	'coso' => array( array( 'ten' => 'FUNFEST PHÚ QUỐC', 'maDonVi' => 'FFPQ', 'phanLoaiLon' => 'KVC MN', 'tenMisa' => 'FUNFEST PQ' ) ),
	'users' => array(
		array( 'ten' => 'Admin', 'pin' => '1111', 'vaiTro' => 'Admin' ),
		array( 'ten' => 'Nguyễn Hữu Thọ', 'pin' => '2222', 'vaiTro' => 'Nhân viên', 'coso' => 'FUNFEST PHÚ QUỐC' ),
		array( 'ten' => 'Kế Toán A', 'pin' => '3333', 'vaiTro' => 'Kế toán cá nhân' ),
	),
) );
VHCP_Cfg::clear_cache();

/* ── 0. Khoá quyền mới có trong danh mục, mặc định đúng ba vai như Trả lại đơn ─────────────── */
$acts = array(); foreach ( VHCP_Cfg::actions() as $a ) { $acts[ $a['key'] ] = $a; }
t( '🔴 có khoá quyền doiLuong', isset( $acts['doiLuong'] ), array_keys( $acts ) );
teq( '   mặc định: Quản lý + Kế toán cá nhân + Kế toán NCC (y như traDon), Nhân viên không', $acts['traDon']['def'], $acts['doiLuong']['def'] );
$qc = VHCP_Cfg::get_quyen_config(); $qdl = null;
foreach ( (array) $qc['actions'] as $a ) { if ( 'doiLuong' === $a['key'] ) { $qdl = $a['perms']; } }
t( '   bản cài cũ (chưa lưu ma trận) vẫn nhận mặc định, và hàng hiện ở bảng Phân quyền', $qdl && ! empty( $qdl['Kế toán cá nhân'] ) && empty( $qdl['Nhân viên'] ), $qdl );

/* ── 1. DỰNG ĐÚNG CA CỦA ANH: đơn trực tiếp, mang tạm ứng xin, đã "gửi quyết toán (trực tiếp)" ── */
/* Dựng qua đường CŨ (trước chốt gốc rễ) bằng cách ghi thẳng sổ — đây là đơn ĐÃ CÓ ngoài đời. */
$d = VHCP_Don::create_don( 'T10/2026 (25/9-1/10/2026)', 'Nguyễn Hữu Thọ', 'tt' );
$M = $d['maDon'];
teq( '   (dựng) đơn mang luồng tt', 'tt', _lg( $M ) );
VHCP_Don::add_line( $M, array( 'coso' => 'FUNFEST PHÚ QUỐC', 'ngay' => '2026-09-25', 'phanLoaiTT' => 'Thanh toán cá nhân',
	'nhom' => 'Chi phí cơ sở', 'noiDung' => 'tấm sắt chống trượt', 'soLuong' => 1, 'donGia' => 1000000, 'thanhTien' => 1000000 ) );
global $wpdb;
$t_tu = VHCP_DB::t( 'tamung' ); $t_don = VHCP_DB::t( 'don' );
$wpdb->query( $wpdb->prepare( "INSERT INTO $t_tu (ma_don, coso, so) VALUES (%s,%s,%f)", $M, 'FUNFEST PHÚ QUỐC', 80000000 ) );
$wpdb->query( $wpdb->prepare( "UPDATE $t_don SET trang_thai=%s, ngay_gui_qt=%s WHERE ma_don=%s", 'Chờ quyết toán', '2026-09-25 15:20:47', $M ) );
teq( '   (dựng) đơn ở "Chờ quyết toán" với 80tr tạm ứng xin', array( 'Chờ quyết toán', 80000000.0 ), array( _tt( $M ), (float) VHCP_Don::tong_xin_hien_tai( $M ) ) );

/* ── 2. Đổi luồng: Kế toán làm được, đơn về Nháp, giữ hạng mục + tạm ứng xin, ghi vết ────────── */
VHCP_Auth::dat_vai_tro( 'Kế toán cá nhân', 'Kế Toán A' );
$r = VHCP_Don::doi_luong_don( $M, 'xyz' );
t( '   mã luồng lạ → chối, câu kể ra ba mã hợp lệ', empty( $r['success'] ) && false !== mb_strpos( $r['error'], 'tt · gt · dc' ), $r );
$r = VHCP_Don::doi_luong_don( $M, 'tt' );
t( '   đổi sang chính luồng đang đi → chối "không có gì để đổi"', empty( $r['success'] ) && false !== mb_strpos( $r['error'], 'không có gì để đổi' ), $r );
$r = VHCP_Don::doi_luong_don( $M, 'gt', 'nhân viên chọn nhầm luồng' );
t( '🔴 đơn TRỰC TIẾP ở "Chờ quyết toán" (chưa cấp đồng nào) → ĐỔI ĐƯỢC sang Qua tạm ứng', ! empty( $r['success'] ), $r );
teq( '   trả về luồng cũ/mới + trạng thái', array( 'tt', 'gt', 'Chờ quyết toán', 'Nháp' ), array( $r['luongCu'], $r['luongMoi'], $r['trangThaiCu'], $r['trangThai'] ) );
teq( '🔴 sổ: luồng = gt, trạng thái = Nháp', array( 'gt', 'Nháp' ), array( _lg( $M ), _tt( $M ) ) );
$g = VHCP_Don::get_don( $M, false );
teq( '🔴 hạng mục GIỮ NGUYÊN (1 dòng 1tr)', 1, count( $g['lines'] ) );
teq( '🔴 tạm ứng xin 80tr GIỮ NGUYÊN — đó là dữ liệu, không phải đường đi', 80000000.0, (float) VHCP_Don::tong_xin_hien_tai( $M ) );
$row = VHCP_Don::don_row( $M );
t( '   mốc gửi quyết toán cũ được xoá', '' === VHCP_Util::fmt( $row['ngay_gui_qt'] ), $row['ngay_gui_qt'] );
$nk = VHCP_Log::get_log( array( 'q' => 'Đổi luồng đơn' ) );
$vet = null; foreach ( (array) $nk['items'] as $l ) { if ( 'Đổi luồng đơn' === $l['hanhDong'] && $M === $l['doiTuong'] ) { $vet = $l; } }
t( '🔴 nhật ký có vết "Đổi luồng đơn" ghi tt → gt, trạng thái cũ, lý do, người làm', $vet && 'Kế Toán A' === $vet['nguoi']
	&& false !== mb_strpos( $vet['chiTiet'], 'Trực tiếp' ) && false !== mb_strpos( $vet['chiTiet'], 'Qua tạm ứng' )
	&& false !== mb_strpos( $vet['chiTiet'], 'Chờ quyết toán' ) && false !== mb_strpos( $vet['chiTiet'], 'nhân viên chọn nhầm luồng' ), $vet );

/* ── 3. Đi tiếp đúng luồng mới: gửi xin tạm ứng ĐƯỢC, gửi quyết toán thẳng thì KHÔNG ────────── */
VHCP_Auth::dat_vai_tro( 'Nhân viên', 'Nguyễn Hữu Thọ' );
$r = VHCP_Don::gui_quyet_toan( $M );
t( '   giờ là đơn qua tạm ứng: gửi quyết toán từ Nháp bị chối (không nhảy cóc)', empty( $r['success'] ), $r );
$r = VHCP_Don::gui_duyet_tam_ung( $M );
t( '🔴 gửi xin tạm ứng ĐƯỢC — 80tr đi vào hàng duyệt', ! empty( $r['success'] ), $r );
teq( '   sang "Chờ duyệt tạm ứng"', 'Chờ duyệt tạm ứng', _tt( $M ) );

/* ── 4. Chốt "tiền đã ra": đã cấp tạm ứng → không đổi được nữa; đã chốt → không ─────────────── */
VHCP_Auth::dat_vai_tro( 'Admin', 'Anh Thắng' );
VHCP_Don::duyet_tam_ung( $M, 'Anh Thắng', 80000000 );
teq( '   (dựng) Chờ cấp tạm ứng', 'Chờ cấp tạm ứng', _tt( $M ) );
$r = VHCP_Don::doi_luong_don( $M, 'dc' );
t( '   Chờ cấp tạm ứng (đã duyệt, chưa cấp) → vẫn đổi được, và GỠ số đã duyệt', ! empty( $r['success'] ) && ! empty( $r['goDuyet'] ), $r );
$row = VHCP_Don::don_row( $M );
t( '   số duyệt/người duyệt đã gỡ, luồng = dc, về Nháp', null === VHCP_Util::blank_or_num( $row['tam_ung_duyet'] ) && '' === (string) $row['nguoi_duyet'] && 'dc' === _lg( $M ) && 'Nháp' === _tt( $M ), $row );
/* đi lại tới đã cấp tiền */
VHCP_Don::gui_duyet_tam_ung( $M ); VHCP_Don::duyet_tam_ung( $M, 'Anh Thắng', 80000000 ); VHCP_Don::cap_tam_ung( $M, 'Anh Thắng', 'Tiền mặt' );
teq( '   (dựng) đã cấp tạm ứng', 'Đã cấp tạm ứng', _tt( $M ) );
$r = VHCP_Don::doi_luong_don( $M, 'tt' );
t( '🔴 tiền ĐÃ cấp → CHỐI, câu chỉ đường "Trả lại đơn"', empty( $r['success'] ) && false !== mb_strpos( $r['error'], 'tiền đã cấp' ) && false !== mb_strpos( $r['error'], 'Trả lại' ), $r );
teq( '   không đụng gì vào đơn', array( 'dc', 'Đã cấp tạm ứng' ), array( _lg( $M ), _tt( $M ) ) );
VHCP_Don::gui_quyet_toan( $M );
$r = VHCP_Don::doi_luong_don( $M, 'tt' );
t( '   đơn duyệt chi ở "Chờ quyết toán" (tiền đã ra) → chối — khác đơn trực tiếp', empty( $r['success'] ), $r );
$wpdb->query( $wpdb->prepare( "UPDATE $t_don SET trang_thai=%s WHERE ma_don=%s", 'Đã quyết toán', $M ) );
$r = VHCP_Don::doi_luong_don( $M, 'tt' );
t( '   đã chốt sổ → chối', empty( $r['success'] ) && false !== mb_strpos( $r['error'], 'chốt' ), $r );

/* ── 5. Đơn luồng RỖNG (cũ, theo khối): so bằng BẢNG luồng, không so mã ─────────────────────── */
$d2 = VHCP_Don::create_don( 'T10/2026 (25/9-1/10/2026)', 'Nguyễn Hữu Thọ', '' );
$M2 = $d2['maDon'];
$wpdb->query( $wpdb->prepare( "UPDATE $t_don SET luong='', khoi='kvc' WHERE ma_don=%s", $M2 ) );
$r = VHCP_Don::doi_luong_don( $M2, 'gt' );
t( '   đơn cũ luồng rỗng ở khối kvc đang đi tạm ứng → chọn "gt" là không đổi gì → chối', empty( $r['success'] ), $r );
$r = VHCP_Don::doi_luong_don( $M2, 'tt' );
t( '   nhưng đổi sang trực tiếp thì được', ! empty( $r['success'] ) && 'tt' === _lg( $M2 ), $r );

/* ── 6. GỐC RỄ — đơn trực tiếp không nhận tạm ứng xin, có số xin thì không gửi thẳng ────────── */
$d3 = VHCP_Don::create_don( 'T10/2026 (25/9-1/10/2026)', 'Nguyễn Hữu Thọ', 'tt' );
$M3 = $d3['maDon'];
VHCP_Auth::dat_vai_tro( 'Nhân viên', 'Nguyễn Hữu Thọ' );
$r = VHCP_Don::set_tam_ung( $M3, 'FUNFEST PHÚ QUỐC', 5000000 );
t( '🔴 đơn TRỰC TIẾP: nhập tạm ứng xin > 0 bị CHỐI ngay lúc gõ, câu chỉ "Đổi luồng"', empty( $r['success'] ) && false !== mb_strpos( $r['error'], 'Đổi luồng' ), $r );
$r = VHCP_Don::set_tam_ung( $M3, 'FUNFEST PHÚ QUỐC', 0 );
t( '   nhưng xoá (0) vẫn được — đường dọn số cũ', ! empty( $r['success'] ), $r );
VHCP_Don::add_line( $M3, array( 'coso' => 'FUNFEST PHÚ QUỐC', 'ngay' => '2026-09-25', 'phanLoaiTT' => 'Thanh toán cá nhân',
	'nhom' => 'Chi phí cơ sở', 'noiDung' => 'x', 'soLuong' => 1, 'donGia' => 200000, 'thanhTien' => 200000 ) );
/* lén ghi số xin thẳng sổ (đơn cũ tồn từ trước chốt) rồi thử gửi thẳng. UPDATE chứ không INSERT:
   `set_tam_ung(…, 0)` ở trên đã tạo hàng (ma_don, coso) — INSERT trùng khoá duy nhất là im lặng không ghi. */
$wpdb->query( $wpdb->prepare( "UPDATE $t_tu SET so=%f WHERE ma_don=%s AND coso=%s", 3000000, $M3, 'FUNFEST PHÚ QUỐC' ) );
teq( '   (dựng) số xin lén = 3tr', 3000000.0, (float) VHCP_Don::tong_xin_hien_tai( $M3 ) );
$r = VHCP_Don::gui_quyet_toan( $M3 );
t( '🔴 đơn trực tiếp CÓ số xin → gửi quyết toán thẳng bị CHỐI, câu nêu số tiền + hai lối ra', empty( $r['success'] ) && false !== mb_strpos( $r['error'], '3.000.000' ) && false !== mb_strpos( $r['error'], 'Đổi luồng' ), $r );
teq( '   đơn vẫn Nháp', 'Nháp', _tt( $M3 ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM $t_tu WHERE ma_don=%s", $M3 ) );
$r = VHCP_Don::gui_quyet_toan( $M3 );
t( '   dọn số xin xong → gửi thẳng được như bài kiem-hai-luong-don', ! empty( $r['success'] ) && 'Chờ quyết toán' === _tt( $M3 ), $r );

/* ── 7. Tuyến API: nhóm người duyệt — Nhân viên không gọi được ──────────────────────────────── */
/* `required_roles()` là hàm riêng — soi qua Reflection như kiem-nhan-ban-cau-hinh.php, không mở public chỉ để kiểm. */
$rr = new ReflectionMethod( 'VHCP_API', 'required_roles' ); $rr->setAccessible( true );
$vai = $rr->invoke( null, 'doiLuongDon' );
t( '🔴 doiLuongDon nằm trong nhóm người duyệt (Admin · Quản lý · hai Kế toán), không có Nhân viên',
	is_array( $vai ) && in_array( 'Admin', $vai, true ) && in_array( 'Kế toán cá nhân', $vai, true ) && ! in_array( 'Nhân viên', $vai, true ), $vai );
teq( '   cùng nhóm với duyetLaiTamUng', $rr->invoke( null, 'duyetLaiTamUng' ), $vai );

if ( $TRUOT ) { echo "\n✗ TRƯỢT " . count( $TRUOT ) . " phép (đạt $DAT):\n"; foreach ( $TRUOT as $x ) { echo "  · $x\n"; } exit( 1 ); }
echo "\n✓ SẠCH — $DAT phép: đổi luồng đơn đang chạy khi tiền chưa ra két; đơn trực tiếp không nhận tạm ứng xin.\n";
