<?php
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * ĐƠN CHI PHÍ CƠ SỞ TỰ QUYẾT TOÁN — THANH TOÁN MỘT LẦN
 *
 * Anh Thắng 11/09/2026: *"Đơn chi phí cơ sở là đơn thanh toán 1 lần, khi cấp tiền và đủ hóa đơn,
 * cấp xong nó tự đẩy sang đã quyết toán luôn"*.
 *
 * =============================================================================================
 * 🔴 HAI ĐIỀU KIỆN, CÁI NÀO XONG SAU THÌ CÁI ĐÓ ĐẨY. Thứ tự thật ngoài đời không cố định: có
 *    tuần kế toán cấp tiền trước rồi nhân viên mới về đính hoá đơn; có tuần ngược lại. Chỉ đẩy
 *    ở một nhánh thì nửa số đơn nằm treo mãi ở "chờ chốt" mà màn không nói vì sao — nên bài
 *    kiểm chạy CẢ HAI thứ tự, và đòi kết quả giống hệt nhau.
 *
 * 🔴 DỰ ÁN SETUP/THÁO DỠ KHÔNG ĐƯỢC ĐỤNG. Nó tạm ứng nhiều đợt, chi rải nhiều tháng; tự chốt sổ
 *    hộ là cướp mất bước đối chiếu của người giữ sổ 141.
 *
 * 🔴 KHÔNG QUYẾT TOÁN HAI LẦN CÙNG MỘT KHOẢN — tất toán gấp đôi số đã ứng thì sổ 141 âm mà
 *    không ai hiểu vì sao.
 *
 * ⚠️ CHẠY THẬT trọn luồng trên CSDL giả: tạo đơn → thêm dòng → xin → duyệt → cấp tiền → hoá đơn.
 *
 * Chạy: php tools/test/kiem-don-coso-tu-quyet-toan.php
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

VHCP_Cfg::seed();

/** Số hàng của hạng mục lớn thứ $k (1-based) — KHÔNG đoán là 1: bảng có mấy hàng khuôn sẵn. */
function hang( $ma, $k = 1 ) {
	$g = VHCP_DuAn::get_du_an( $ma );
	$i = 0;
	foreach ( (array) $g['lines'] as $l ) {
		if ( trim( (string) $l['capCha'] ) !== '' ) { continue; }
		$i++;
		if ( $i === $k ) { return (int) $l['row']; }
	}
	return 0;
}
/** Số đợt quyết toán của một hạng mục — `hm` nằm TRONG từng dòng, không phải một bảng riêng. */
function qt_dot( $ma, $row ) {
	$g = VHCP_DuAn::get_du_an( $ma );
	foreach ( (array) $g['lines'] as $l ) {
		if ( (int) $l['row'] !== (int) $row ) { continue; }
		return isset( $l['hm']['qtDot'] ) ? (int) $l['hm']['qtDot'] : 0;
	}
	return 0;
}
/** Lệnh quyết toán của đơn: [dot => tt]. */
function lenh_qt( $ma ) {
	$ra = array();
	foreach ( VHCP_DuAn::get_dot( $ma, 'qt' ) as $k => $d ) { $ra[ (int) $k ] = (string) $d['tt']; }
	return $ra;
}
/** Dựng một đơn cơ sở có một hạng mục, đã xin + duyệt tạm ứng. Trả [maDA, row]. */
function don_da_duyet( $ten ) {
	VHCP_Auth::dat_vai_tro( 'Admin', 'Sếp' );
	$d  = VHCP_DuAn::tao_don_coso( $ten, 'Sếp', '07/09/2026', '13/09/2026' );
	$ma = $d['maDA'];
	VHCP_DuAn::add_line( $ma, array( 'noiDung' => 'Cáp màn hình', 'gian' => 'VR SC Vivo Q7',
		'soLuong' => 1, 'donGia' => 500000, 'duToan' => 500000 ) );
	$r = hang( $ma );
	VHCP_DuAn::xin_tam_ung_dot( $ma, array( $r ) );
	VHCP_DuAn::dat_tt_dot( $ma, 1, 'duyet' );
	return array( $ma, $r );
}

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 1. THỨ TỰ A — HOÁ ĐƠN TRƯỚC, CẤP TIỀN SAU
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
list( $maA, $rA ) = don_da_duyet( 'Chi phí cơ sở tuần A' );

/* Chốt hoá đơn TRƯỚC khi cấp tiền: chưa được quyết toán — tiền chưa ra khỏi két. */
$k = VHCP_DuAn::dat_hm( $maA, $rA, 'xong', array( 'hoaDon' => 'https://x/hd-A.jpg' ) );
t( 'chốt hoá đơn được', ! empty( $k['success'] ), $k );
teq( '🔴 mới có hoá đơn, CHƯA cấp tiền → chưa quyết toán', 0, qt_dot( $maA, $rA ) );
teq( '   và chưa có lệnh quyết toán nào', array(), lenh_qt( $maA ) );

/* Giờ cấp tiền → phải tự đẩy sang đã quyết toán. */
$c = VHCP_DuAn::dat_tt_dot( $maA, 1, 'ung', array( 'unc' => 'UNC-A' ) );
t( 'cấp tiền được', ! empty( $c['success'] ), $c );
teq( '🔴 cấp tiền xong: TỰ quyết toán 1 hạng mục', 1, (int) $c['tuQuyetToan'] );
t( '   hạng mục đã mang số đợt quyết toán', qt_dot( $maA, $rA ) > 0, qt_dot( $maA, $rA ) );
teq( '🔴 và lệnh quyết toán đã ở trạng thái CHỐT SỔ', array( 1 => 'xong' ), lenh_qt( $maA ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 2. THỨ TỰ B — CẤP TIỀN TRƯỚC, HOÁ ĐƠN SAU
 *
 * 🔴 Phải ra KẾT QUẢ GIỐNG HỆT thứ tự A. Thiếu phép này thì một bản vá chỉ móc ở nhánh cấp tiền
 *    vẫn xanh, trong khi nửa số đơn ngoài đời đi theo thứ tự ngược lại và nằm treo mãi.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
list( $maB, $rB ) = don_da_duyet( 'Chi phí cơ sở tuần B' );

$c = VHCP_DuAn::dat_tt_dot( $maB, 1, 'ung', array( 'unc' => 'UNC-B' ) );
teq( '🔴 cấp tiền mà chưa có hoá đơn → CHƯA quyết toán', 0, (int) $c['tuQuyetToan'] );
teq( '   chưa có lệnh quyết toán nào', array(), lenh_qt( $maB ) );

$k = VHCP_DuAn::dat_hm( $maB, $rB, 'xong', array( 'hoaDon' => 'https://x/hd-B.jpg' ) );
teq( '🔴 đính nốt hoá đơn: TỰ quyết toán ngay', 1, (int) $k['tuQuyetToan'] );
teq( '   lệnh quyết toán đã chốt sổ', array( 1 => 'xong' ), lenh_qt( $maB ) );
t( '   hạng mục mang số đợt quyết toán', qt_dot( $maB, $rB ) > 0, qt_dot( $maB, $rB ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 3. 🔴 KHÔNG QUYẾT TOÁN HAI LẦN
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
$lai = VHCP_DuAn::tu_quyet_toan_coso( $maA, array( $rA ) );
teq( '🔴 gọi lại lần nữa: không chốt thêm hạng mục nào', 0, (int) $lai['so'] );
teq( '   và vẫn đúng MỘT lệnh quyết toán', 1, count( lenh_qt( $maA ) ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 4. 🔴 DỰ ÁN SETUP/THÁO DỠ KHÔNG ĐƯỢC TỰ CHỐT
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
VHCP_Auth::dat_vai_tro( 'Admin', 'Sếp' );
$da = VHCP_DuAn::create_du_an( 'Setup lắp đặt', 'Setup gian ADV', 'Sếp' );
$maS = $da['maDA'];
VHCP_DuAn::add_line( $maS, array( 'noiDung' => 'Thi công sàn', 'soLuong' => 1, 'donGia' => 900000, 'duToan' => 900000 ) );
$rS = hang( $maS );
VHCP_DuAn::xin_tam_ung_dot( $maS, array( $rS ) );
VHCP_DuAn::dat_tt_dot( $maS, 1, 'duyet' );
$c = VHCP_DuAn::dat_tt_dot( $maS, 1, 'ung', array( 'unc' => 'UNC-S' ) );
teq( 'dự án Setup: cấp tiền KHÔNG tự quyết toán', 0, (int) $c['tuQuyetToan'] );
$k = VHCP_DuAn::dat_hm( $maS, $rS, 'xong', array( 'hoaDon' => 'https://x/hd-S.jpg' ) );
teq( '🔴 dự án Setup: chốt hoá đơn cũng KHÔNG tự quyết toán', 0, (int) $k['tuQuyetToan'] );
teq( '   và không có lệnh quyết toán nào', array(), lenh_qt( $maS ) );
/* Gọi thẳng hàm cũng phải chối — luật nằm trong hàm, không nằm ở chỗ gọi. */
teq( '🔴 gọi thẳng tu_quyet_toan_coso() cho dự án Setup cũng trả 0', 0,
	(int) VHCP_DuAn::tu_quyet_toan_coso( $maS, array( $rS ) )['so'] );
/* Đường tay vẫn còn nguyên cho dự án: nhân viên tích gửi, kế toán chốt. */
$x = VHCP_DuAn::xin_quyet_toan_dot( $maS, array( $rS ) );
t( '   nhưng đường gửi quyết toán TAY vẫn chạy', ! empty( $x['success'] ), $x );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 5. 🔴 CHƯA CẤP TIỀN THÌ KHÔNG CHỐT, DÙ ĐÃ CÓ HOÁ ĐƠN
 *
 * Lệnh mới duyệt (chưa 'ung') là tiền còn trong két. Chốt quyết toán lúc ấy là nói đã tất toán
 * một khoản chưa ai chi.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
list( $maC, $rC ) = don_da_duyet( 'Chi phí cơ sở tuần C' );
VHCP_DuAn::dat_hm( $maC, $rC, 'xong', array( 'hoaDon' => 'https://x/hd-C.jpg' ) );
teq( '🔴 lệnh mới DUYỆT (chưa cấp tiền) → không tự chốt', 0,
	(int) VHCP_DuAn::tu_quyet_toan_coso( $maC, array( $rC ) )['so'] );
teq( '   không có lệnh quyết toán nào', array(), lenh_qt( $maC ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 6. ĐƠN NHIỀU HẠNG MỤC — chỉ chốt cái nào ĐỦ, không kéo cả đơn
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
VHCP_Auth::dat_vai_tro( 'Admin', 'Sếp' );
$d  = VHCP_DuAn::tao_don_coso( 'Chi phí cơ sở tuần D', 'Sếp', '07/09/2026', '13/09/2026' );
$maD = $d['maDA'];
VHCP_DuAn::add_line( $maD, array( 'noiDung' => 'Cáp màn hình', 'gian' => 'G1', 'soLuong' => 1, 'donGia' => 500000 ) );
VHCP_DuAn::add_line( $maD, array( 'noiDung' => 'Mua bàn phím', 'gian' => 'G2', 'soLuong' => 1, 'donGia' => 26000 ) );
$r1 = hang( $maD, 1 ); $r2 = hang( $maD, 2 );
VHCP_DuAn::xin_tam_ung_dot( $maD, array( $r1, $r2 ) );
VHCP_DuAn::dat_tt_dot( $maD, 1, 'duyet' );
/* Chỉ hạng mục 1 có hoá đơn. */
VHCP_DuAn::dat_hm( $maD, $r1, 'xong', array( 'hoaDon' => 'https://x/hd-D1.jpg' ) );
$c = VHCP_DuAn::dat_tt_dot( $maD, 1, 'ung', array( 'unc' => 'UNC-D' ) );
teq( '🔴 chỉ chốt đúng hạng mục CÓ hoá đơn', 1, (int) $c['tuQuyetToan'] );
t( '   hạng mục 1 đã quyết toán',      qt_dot( $maD, $r1 ) > 0, qt_dot( $maD, $r1 ) );
teq( '🔴 hạng mục 2 chưa có hoá đơn → CHƯA quyết toán', 0, qt_dot( $maD, $r2 ) );
/* Đính nốt hoá đơn cho hạng mục 2 → nó tự chốt ở một lệnh riêng. */
$k = VHCP_DuAn::dat_hm( $maD, $r2, 'xong', array( 'hoaDon' => 'https://x/hd-D2.jpg' ) );
teq( '   đính nốt hoá đơn thì nó tự chốt', 1, (int) $k['tuQuyetToan'] );
teq( '🔴 thành HAI lệnh quyết toán, cả hai đều chốt sổ',
	array( 1 => 'xong', 2 => 'xong' ), lenh_qt( $maD ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 6b. HẠNG MỤC LẠ / DANH SÁCH RỖNG → TRẢ 0, KHÔNG NỔ
 *
 * Chỗ gọi truyền số hàng lấy từ một lệnh cũ, mà hạng mục ấy có thể đã bị xoá. Ném lỗi ở đây là
 * làm gãy chính lượt cấp tiền đang chạy.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
teq( 'danh sách rỗng → 0',        0, (int) VHCP_DuAn::tu_quyet_toan_coso( $maA, array() )['so'] );
teq( 'hàng không có thật → 0',    0, (int) VHCP_DuAn::tu_quyet_toan_coso( $maA, array( 9999 ) )['so'] );
teq( 'mã đơn không có thật → 0',  0, (int) VHCP_DuAn::tu_quyet_toan_coso( 'KHONG-CO', array( 1 ) )['so'] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 6c. 🔴 HẠNG MỤC KHÔNG THUỘC LỆNH TẠM ỨNG NÀO THÌ KHÔNG TỰ CHỐT
 *
 * Đơn 🏢 TRỰC TIẾP (kế toán trả thẳng nhà cung cấp) không có lệnh tạm ứng nào cả — kế toán tự
 * tích "đã chi" rồi khoá. Tiền ấy không qua tay nhân viên nên KHÔNG có khoản tạm ứng nào để
 * tất toán; tự chốt quyết toán ở đó là dựng ra một lệnh tất toán cho khoản chưa từng ứng.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
VHCP_Auth::dat_vai_tro( 'Admin', 'Sếp' );
$dG  = VHCP_DuAn::tao_don_coso( 'Chi phí cơ sở tuần G', 'Sếp', '07/09/2026', '13/09/2026' );
$maG = $dG['maDA'];
VHCP_DuAn::add_line( $maG, array( 'noiDung' => 'Thuê xe', 'gian' => 'G9', 'soLuong' => 1,
	'donGia' => 300000, 'hinhThuc' => 'Trực tiếp' ) );
$rG = hang( $maG );
$kG = VHCP_DuAn::dat_hm( $maG, $rG, 'xong', array( 'hoaDon' => 'https://x/hd-G.jpg', 'unc' => 'UNC-G' ) );
t( 'kế toán khoá đơn NCC được', ! empty( $kG['success'] ), $kG );
teq( '🔴 hạng mục KHÔNG thuộc lệnh tạm ứng nào → không tự quyết toán', 0, (int) $kG['tuQuyetToan'] );
teq( '   và không có lệnh quyết toán nào', array(), lenh_qt( $maG ) );
teq( '   gọi thẳng hàm cũng trả 0', 0, (int) VHCP_DuAn::tu_quyet_toan_coso( $maG, array( $rG ) )['so'] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 7. NHÂN VIÊN ĐÍNH HOÁ ĐƠN CŨNG CHỐT ĐƯỢC — không vướng cửa "chỉ kế toán chốt sổ"
 *
 * 🔴 `dat_tt_qt()` gác quyền chỉ kế toán; nếu đường tự động đi qua cửa ấy thì nhân viên đính
 *    hoá đơn xong nhận một câu chối khó hiểu, và đơn nằm treo.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
list( $maE, $rE ) = don_da_duyet( 'Chi phí cơ sở tuần E' );
VHCP_DuAn::dat_tt_dot( $maE, 1, 'ung', array( 'unc' => 'UNC-E' ) );
VHCP_Auth::dat_vai_tro( 'Nhân viên', 'Bạn Nhân Viên' );
$k = VHCP_DuAn::dat_hm( $maE, $rE, 'xong', array( 'hoaDon' => 'https://x/hd-E.jpg' ) );
t( '🔴 NHÂN VIÊN đính hoá đơn: không bị chối', ! empty( $k['success'] ), $k );
teq( '   và vẫn tự quyết toán được', 1, (int) $k['tuQuyetToan'] );
teq( '   lệnh đã chốt sổ',           array( 1 => 'xong' ), lenh_qt( $maE ) );

/* ─────────────────────────────────────────────────────────────────────────────────────────── */
echo "\n";
if ( $TRUOT ) {
	echo "❌ TRƯỢT " . count( $TRUOT ) . " / " . ( $DAT + count( $TRUOT ) ) . "\n";
	foreach ( $TRUOT as $x ) { echo "   • $x\n"; }
	exit( 1 );
}
echo "✅ ĐẠT $DAT / $DAT — đơn cơ sở tự quyết toán khi đủ tiền + hoá đơn, dự án vẫn đi tay\n";
