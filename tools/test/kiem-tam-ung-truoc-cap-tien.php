<?php
/**
 * KIỂM LUẬT "CHƯA CẤP TIỀN THÌ CHƯA CÓ THỰC CHI" (anh Thắng 06/09/2026).
 *
 * =============================================================================================
 * 🔴 ĐẦU BÀI, NGUYÊN VĂN
 * =============================================================================================
 * *"Trước đã cấp tiền hệ thống phải ghi nhận tiền tạm ứng. Không tính tiền thực chi. (vì nhập
 * chi tiết đơn là các bạn nhập để lưu trữ lên) còn tạm ứng là để kế toán chốt số tạm ứng của
 * tuần đó. Còn khi đã cấp tiền lúc này kế toán mới quan tâm thực chi là bao nhiêu và thừa
 * thiếu bao nhiêu."*
 *
 * =============================================================================================
 * 🔴 CÁI SAI GỐC RỄ MÀ BÀI NÀY CANH
 * =============================================================================================
 * Quy ước cũ nằm rải rác sáu chỗ chép tay:
 *
 *     $eff = ( $tm === null ) ? $tt : $tm;   // chưa gõ thực mua -> LẤY LUÔN SỐ XIN
 *
 * Với đơn ĐÃ cấp tiền thì đúng: mua rồi mà chưa kịp gõ số thì tạm coi bằng số xin. Nhưng với
 * đơn còn Nháp / Chờ duyệt / Chờ cấp thì nhân viên chưa mua gì cả — `thuc_mua` NULL là vì
 * chưa có gì để mua. Hệ lấy nguyên KẾ HOẠCH MUA làm TIỀN ĐÃ TIÊU, rồi tính thừa/thiếu trên đó.
 *
 * Hậu quả đi rất xa, và không chỗ nào tự kêu:
 *   · Màn Quyết toán của một đơn còn nháp đã báo "Thiếu N — kế toán chi bù cho NV".
 *   · Bảng Thừa/thiếu tuần đầy dòng chưa ai đưa đồng nào.
 *   · Con số chênh ấy chảy tiếp vào phép BÙ TRỪ LUÂN CHUYỂN sang tuần sau.
 *   · Báo cáo tổng quan và màn tra theo mã tài khoản cộng luôn mọi đơn nháp vào cột thực chi.
 *
 * =============================================================================================
 * ⚠️ HAI VẾ, THIẾU VẾ NÀO CŨNG HỎNG
 * =============================================================================================
 * Bài này luôn canh CẢ HAI: chưa cấp thì 0, VÀ cấp rồi thì con số hiện ra ĐÚNG BẰNG cũ. Chỉ
 * canh vế đầu thì một cái chặn quá tay (chặn luôn đơn đã cấp) vẫn xanh — mà đó mới đúng lúc
 * kế toán cần nhìn thực chi.
 *
 * Chạy: php tools/test/kiem-tam-ung-truoc-cap-tien.php
 */

$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';

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

vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );
vhcp_test_dat_gio( '2026-09-06 09:00:00' );
VHCP_Auth::dat_vai_tro( 'Admin', 'Admin' );
global $wpdb;

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * PHẦN 1 — `da_cap_tien()`: CHỐT DÙNG CHUNG, hàm thuần
 *
 * ⚠️ ĐỌC DÃY TRẠNG THÁI TỪ CHÍNH `TT_LUONG`, không gõ tay lại bảy chuỗi. Gõ tay thì thêm một
 *    chặng vào luồng mà bài kiểm không biết — và cái mới ấy lại đúng là chặng dễ sai nhất.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */

$LUONG = VHCP_Don::TT_LUONG;
$MOC   = array_search( 'Đã cấp tạm ứng', $LUONG, true );
t( '🔴 "Đã cấp tạm ứng" có thật trong dãy trạng thái', false !== $MOC, $LUONG );
t( 'và nó không phải chặng đầu (phải có chặng trước nó để mà chặn)', $MOC > 0, $MOC );
t( 'cũng không phải chặng cuối (phải có chặng sau nó để mà cho qua)', $MOC < count( $LUONG ) - 1, $MOC );

foreach ( $LUONG as $i => $st ) {
	$mong = ( $i >= $MOC );
	teq( 'da_cap_tien("' . $st . '")', $mong, VHCP_Don::da_cap_tien( $st ) );
}

/* Đơn cũ chưa có trạng thái = Nháp. Và trạng thái GÕ TAY / dữ liệu cũ lạ hoắc thì đoán về
   phía AN TOÀN: coi như chưa cấp. Đoán nhầm hướng kia là đem kế hoạch mua vào sổ thực chi và
   không ai thấy; đoán nhầm hướng này chỉ là một số 0 nhìn thấy được, người ta hỏi ngay. */
teq( 'trạng thái rỗng = Nháp = chưa cấp', false, VHCP_Don::da_cap_tien( '' ) );
teq( 'null cũng vậy',                     false, VHCP_Don::da_cap_tien( null ) );
teq( '🔴 trạng thái lạ -> coi như CHƯA cấp', false, VHCP_Don::da_cap_tien( 'Đã cấp tiền rồi nhé' ) );
teq( 'chuỗi gần giống cũng không được ăn may', false, VHCP_Don::da_cap_tien( 'Đã cấp tạm ứng cho NV' ) );
teq( 'thừa khoảng trắng hai đầu vẫn nhận ra', true, VHCP_Don::da_cap_tien( '  Đã cấp tạm ứng  ' ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * PHẦN 2 — `thuc_chi()`: QUY ƯỚC MỘT DÒNG, hàm thuần
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */

teq( 'chưa cấp: chưa gõ thực mua -> 0 (KHÔNG lấy số xin)',
	0, VHCP_Don::thuc_chi( 500000, null, 'Nháp' ) );
teq( 'chưa cấp: ô thực mua để trống -> 0',
	0, VHCP_Don::thuc_chi( 500000, '', 'Chờ duyệt tạm ứng' ) );
/* 🔴 Ca này quan trọng: NV đã gõ số thực mua từ lúc đơn còn ở "Chờ cấp" (màn cho gõ). Tiền
   vẫn CHƯA ra khỏi két, nên vẫn chưa phải thực chi. */
teq( '🔴 chưa cấp: DÙ ĐÃ gõ thực mua vẫn 0',
	0, VHCP_Don::thuc_chi( 500000, 470000, 'Chờ cấp tạm ứng' ) );

teq( 'đã cấp: chưa gõ thực mua -> tạm lấy số xin', 500000,
	VHCP_Don::thuc_chi( 500000, null, 'Đã cấp tạm ứng' ) );
teq( 'đã cấp: gõ rồi -> lấy số đã gõ', 470000,
	VHCP_Don::thuc_chi( 500000, 470000, 'Đã cấp tạm ứng' ) );
/* 🔴 NULL khác 0. NULL = chưa ai gõ (tạm lấy số xin). 0 = đã gõ, và gõ là KHÔNG MUA ĐỒNG NÀO
   (hàng không có, hủy mua) — phải ra 0, không được lấp bằng số xin. */
teq( '🔴 đã cấp: gõ SỐ 0 -> đúng 0, không lấp bằng số xin', 0,
	VHCP_Don::thuc_chi( 500000, 0, 'Đã cấp tạm ứng' ) );
teq( 'và "0" dạng chuỗi cũng vậy', 0,
	VHCP_Don::thuc_chi( 500000, '0', 'Đã cấp tạm ứng' ) );
teq( 'quyết toán rồi thì vẫn tính', 470000,
	VHCP_Don::thuc_chi( 500000, 470000, 'Đã quyết toán' ) );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * PHẦN 3 — CHẠY THẬT MỘT ĐƠN, ĐI TỪNG CHẶNG
 *
 * Không dựng số liệu bằng tay: tạo đơn, thêm hạng mục, rồi ĐẨY QUA ĐÚNG CÁC CỬA mà người thật
 * bấm. Có thế mới bắt được cửa nào quên áp luật.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */

$CS = 'FUNZONE VŨNG TÀU';
VHCP_Cfg::save_config( array(
	'coso' => array( array( 'ten' => $CS, 'maDonVi' => 'FZ_VT', 'phanLoaiLon' => 'FUNZONE' ) ),
) );

$don = VHCP_Don::create_don( 'T9/2026 (1/9-6/9/2026)', 'Em Nhân Viên' );
$MA  = $don['maDon'];
VHCP_Don::add_line( $MA, array( 'coso' => $CS, 'ngay' => '2026-09-02',
	'phanLoaiTT' => 'Thanh toán cá nhân', 'nhom' => 'Chi phí cơ sở', 'noiDung' => 'Vật tư',
	'soLuong' => 1, 'donGia' => 2000000, 'thanhTien' => 2000000 ) );
VHCP_Don::set_tam_ung( $MA, $CS, 2000000 );

$mot_don = function ( $ma ) {
	foreach ( VHCP_Don::list_dons() as $x ) { if ( $x['maDon'] === $ma ) { return $x; } }
	return null;
};

/* --- CÒN NHÁP --- */
$g = VHCP_Don::get_don( $MA );
$l = $mot_don( $MA );
teq( 'đơn đang ở Nháp', 'Nháp', (string) $g['don']['trangThai'] );
teq( '🔴 tạm ứng GHI NHẬN ĐỦ ngay từ Nháp', 2000000, (int) $g['tongCN']['tamUng'] );
teq( '🔴 nhưng thực chi = 0',                 0,       (int) $g['tongCN']['thucChi'] );
/* Chênh lệch phải là 0, KHÔNG phải bằng tạm ứng: thực chi 0 mà cứ trừ thì ra đúng số tạm
   ứng, và màn hình đọc thành "thừa cả cục" — trong khi chưa ai đưa đồng nào thì không thể
   thừa, cũng không thể thiếu. */
teq( '🔴 chênh lệch = 0, KHÔNG phải "thừa cả cục tạm ứng"', 0, (int) $g['tongCN']['chenhLech'] );
teq( 'cờ daCapTien = 0', 0, (int) $g['daCapTien'] );
teq( '⚠️ và KHÔNG bày bảng đối chiếu theo cơ sở', 0, count( $g['reconCN'] ) );
/* ⚠️ Dòng hạng mục vẫn NGUYÊN trong sổ — anh Thắng: "nhập chi tiết đơn là các bạn nhập để lưu
   trữ lên". Cái đổi là nó không được cộng vào thực chi, không phải nó biến mất. */
teq( '⚠️ dòng hạng mục vẫn còn nguyên trong đơn', 1, count( $g['lines'] ) );
teq( 'và vẫn mang đủ số tiền xin', 2000000, (int) VHCP_Util::num( $g['lines'][0]['thanhTien'] ) );

teq( 'bảng danh sách: thực chi 0', 0, (int) $l['thucChi'] );
teq( 'bảng danh sách: tạm ứng đủ', 2000000, (int) $l['tamUng'] );
teq( 'bảng danh sách: chênh lệch 0', 0, (int) $l['chenhLech'] );
teq( 'bảng danh sách: cờ daCapTien = 0', 0, (int) $l['daCapTien'] );

/* --- ĐI TỪNG CHẶNG: hai cửa TRƯỚC mốc cấp tiền vẫn phải im lặng ---
   Đây là chỗ dễ sót nhất: người sửa thường chỉ chặn đúng "Nháp" rồi quên hai chặng giữa. */
VHCP_Don::gui_duyet_tam_ung( $MA );
teq( 'đơn sang Chờ duyệt tạm ứng', 'Chờ duyệt tạm ứng', (string) VHCP_Don::don_row( $MA )['trang_thai'] );
teq( '🔴 Chờ duyệt: thực chi vẫn 0', 0, (int) VHCP_Don::get_don( $MA )['tongCN']['thucChi'] );
teq( 'và bảng danh sách cũng nói 0', 0, (int) $mot_don( $MA )['thucChi'] );

VHCP_Don::duyet_tam_ung( $MA, 'Chị Kế Toán', '' );
teq( 'đơn sang Chờ cấp tạm ứng', 'Chờ cấp tạm ứng', (string) VHCP_Don::don_row( $MA )['trang_thai'] );
teq( '🔴 Chờ cấp (đã duyệt, tiền CHƯA ra khỏi két): thực chi vẫn 0',
	0, (int) VHCP_Don::get_don( $MA )['tongCN']['thucChi'] );
teq( 'và bảng danh sách cũng nói 0', 0, (int) $mot_don( $MA )['thucChi'] );

/* --- CẤP TIỀN: BÂY GIỜ MỚI CÓ THỰC CHI --- */
VHCP_Don::cap_tam_ung( $MA, 'Chị Kế Toán', 'Tiền mặt' );
$g = VHCP_Don::get_don( $MA );
$l = $mot_don( $MA );
teq( 'đơn sang Đã cấp tạm ứng', 'Đã cấp tạm ứng', (string) $g['don']['trangThai'] );
teq( 'cờ daCapTien = 1', 1, (int) $g['daCapTien'] );
teq( '🔴 thực chi hiện ra đủ', 2000000, (int) $g['tongCN']['thucChi'] );
teq( 'tạm ứng vẫn nguyên', 2000000, (int) $g['tongCN']['tamUng'] );
teq( 'chênh lệch khớp: 2.000.000 − 2.000.000', 0, (int) $g['tongCN']['chenhLech'] );
teq( '⚠️ và BÂY GIỜ mới bày bảng đối chiếu theo cơ sở', 1, count( $g['reconCN'] ) );
teq( 'bảng ấy gọi đúng tên cơ sở', $CS, (string) $g['reconCN'][0]['coso'] );
teq( 'bảng danh sách: thực chi khớp với màn đơn', 2000000, (int) $l['thucChi'] );
teq( 'bảng danh sách: cờ daCapTien = 1', 1, (int) $l['daCapTien'] );

/* --- THỪA / THIẾU: gõ thực mua ít hơn thì phải ra THỪA --- */
$_line_id = (string) $g['lines'][0]['id'];
VHCP_Don::set_line_thuc_mua( $_line_id, 1700000, 'Em Nhân Viên' );
$g = VHCP_Don::get_don( $MA );
teq( 'gõ thực mua 1.700.000 -> thực chi theo đúng', 1700000, (int) $g['tongCN']['thucChi'] );
teq( '🔴 thừa 300.000 (tạm ứng 2.000.000 − thực chi 1.700.000)', 300000, (int) $g['tongCN']['chenhLech'] );
teq( 'bảng danh sách cũng ra 300.000, không lệch', 300000, (int) $mot_don( $MA )['chenhLech'] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * PHẦN 4 — 🔴 BA MÀN ĐỌC CHUNG MỘT LUẬT
 *
 * Đây là bài học đắt nhất của kho này: anh Thắng gửi ảnh 31/08/2026 *"2 có số tổng tạm ứng
 * khác nhau"* — hai màn hình cùng một đơn, hai con số. Lần đó là tạm ứng; luật thực chi mới
 * này có tới SÁU bản chép, nên phải canh CHÚNG BẰNG NHAU, không chỉ canh từng cái đúng.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */

$don2 = VHCP_Don::create_don( 'T9/2026 (1/9-6/9/2026)', 'Em Nhân Viên' );
$MA2  = $don2['maDon'];
VHCP_Don::add_line( $MA2, array( 'coso' => $CS, 'ngay' => '2026-09-03',
	'phanLoaiTT' => 'Thanh toán cá nhân', 'nhom' => 'Chi phí cơ sở', 'noiDung' => 'Bàn ghế',
	'soLuong' => 1, 'donGia' => 900000, 'thanhTien' => 900000 ) );

/* Con số của MỘT cơ sở, đọc qua ba đường khác nhau. `$MA` (đã cấp, thực mua 1.700.000) phải
   được đếm; `$MA2` (còn nháp, 900.000) thì không. */
$tong_finance = function () use ( $CS ) {
	return (int) VHCP_Report::finance( array( 'coso' => $CS ) )['totals']['thucTe'];
};
$tong_trama = function () use ( $CS ) {
	$s = 0;
	foreach ( VHCP_TraMa::all_lines() as $r ) {
		if ( $r['mang'] === 'don' && $r['coso'] === $CS ) { $s += (int) VHCP_Util::num( $r['tien'] ); }
	}
	return $s;
};
$tong_list = function () use ( $MA, $MA2 ) {
	$s = 0;
	foreach ( VHCP_Don::list_dons() as $x ) {
		if ( $x['maDon'] === $MA || $x['maDon'] === $MA2 ) { $s += (int) $x['thucChi']; }
	}
	return $s;
};

/* Màn "gian" (báo cáo gom mọi mảng của một cơ sở) — khối 📝 Đơn vận hành trong đó. */
$tong_gian = function () use ( $CS ) {
	$s = 0;
	foreach ( VHCP_Report::gian_report( $CS )['sections'] as $sec ) {
		if ( mb_strpos( $sec['module'], 'Đơn vận hành' ) !== false ) { $s += (int) $sec['tot']; }
	}
	return $s;
};
/* Chi phí vận hành THEO TUẦN — tuần chứa 02/09/2026 bắt đầu Thứ Hai 31/08/2026. */
$tong_tuan = function () use ( $CS ) {
	$s = 0;
	foreach ( VHCP_Report::van_hanh_tuan( '31/08/2026' )['list'] as $m ) {
		if ( $m['coso'] === $CS ) { $s += (int) $m['vh']; }
	}
	return $s;
};

teq( '🔴 báo cáo tổng quan: chỉ đếm đơn đã cấp tiền', 1700000, $tong_finance() );
teq( '🔴 tra theo mã tài khoản: cũng vậy',             1700000, $tong_trama() );
teq( '🔴 bảng danh sách đơn: cũng vậy',                1700000, $tong_list() );
teq( '🔴 báo cáo một gian/cơ sở: cũng vậy',            1700000, $tong_gian() );
teq( '🔴 chi phí vận hành theo TUẦN: cũng vậy',        1700000, $tong_tuan() );

/* --- ĐỐI CHỨNG: cấp tiền cho đơn thứ hai thì CẢ BA cùng nhúc nhích, cùng một lượng --- */
VHCP_Don::set_tam_ung( $MA2, $CS, 900000 );
VHCP_Don::gui_duyet_tam_ung( $MA2 );
VHCP_Don::duyet_tam_ung( $MA2, 'Chị Kế Toán', '' );
VHCP_Don::cap_tam_ung( $MA2, 'Chị Kế Toán', 'Tiền mặt' );

teq( '🔴 cấp tiền đơn 2: báo cáo tổng quan cộng thêm 900.000', 2600000, $tong_finance() );
teq( '🔴 tra theo mã tài khoản cũng cộng thêm',                2600000, $tong_trama() );
teq( '🔴 bảng danh sách đơn cũng cộng thêm',                   2600000, $tong_list() );
teq( '🔴 báo cáo một gian/cơ sở cũng cộng thêm',               2600000, $tong_gian() );
teq( '🔴 chi phí vận hành theo tuần cũng cộng thêm',           2600000, $tong_tuan() );

/* --- VÀ CỘT "XIN" KHÔNG BAO GIỜ BỊ LUẬT NÀY ĐỘNG TỚI --- */
$don3 = VHCP_Don::create_don( 'T9/2026 (1/9-6/9/2026)', 'Em Nhân Viên Khác' );
$MA3  = $don3['maDon'];
VHCP_Don::add_line( $MA3, array( 'coso' => $CS, 'ngay' => '2026-09-04',
	'phanLoaiTT' => 'Thanh toán cá nhân', 'nhom' => 'Chi phí cơ sở', 'noiDung' => 'Đèn',
	'soLuong' => 1, 'donGia' => 400000, 'thanhTien' => 400000 ) );
$fr = VHCP_Report::finance( array( 'coso' => $CS ) );
teq( '⚠️ cột XIN vẫn đếm cả đơn còn nháp (kế hoạch, có từ lúc gõ đơn)',
	3300000, (int) $fr['totals']['xin'] );
teq( 'còn cột thực tế thì không nhúc nhích vì đơn 3', 2600000, (int) $fr['totals']['thucTe'] );

/* --- 🔴 ĐƯỜNG NHÀ CUNG CẤP CŨNG PHẢI IM LẶNG. Đơn NCC không đi qua tay nhân viên cầm tiền,
   nên rất dễ nghĩ "khỏi cần chặn" — nhưng nó vẫn nằm trong cùng bảng thực chi của kế toán. */
VHCP_Don::add_line( $MA3, array( 'coso' => $CS, 'ngay' => '2026-09-04',
	'phanLoaiTT' => 'Nhà cung cấp', 'nhom' => 'SP Đồ uống - NCC', 'noiDung' => 'Nước ngọt',
	'soLuong' => 1, 'donGia' => 600000, 'thanhTien' => 600000 ) );
$g3 = VHCP_Don::get_don( $MA3 );
teq( 'đơn 3 còn Nháp', 'Nháp', (string) $g3['don']['trangThai'] );
teq( '🔴 thực chi NCC = 0 khi chưa cấp tiền', 0, (int) $g3['tongNCC']['thucChi'] );
teq( '⚠️ và KHÔNG bày bảng đối chiếu NCC', 0, count( $g3['reconNCC'] ) );

VHCP_Don::set_tam_ung( $MA3, $CS, 400000 );
VHCP_Don::gui_duyet_tam_ung( $MA3 );
VHCP_Don::duyet_tam_ung( $MA3, 'Chị Kế Toán', '' );
VHCP_Don::cap_tam_ung( $MA3, 'Chị Kế Toán', 'Tiền mặt' );
$g3 = VHCP_Don::get_don( $MA3 );
teq( '🔴 cấp tiền rồi: thực chi NCC hiện ra đủ', 600000, (int) $g3['tongNCC']['thucChi'] );
teq( '⚠️ và BÂY GIỜ mới bày bảng đối chiếu NCC', 1, count( $g3['reconNCC'] ) );
teq( 'phần cá nhân của đơn ấy cũng hiện ra', 400000, (int) $g3['tongCN']['thucChi'] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * PHẦN 5 — 🔴 GỠ LƯỢT CẤP THÌ THỰC CHI PHẢI TỤT LẠI
 *
 * Admin gỡ lượt cấp (`go_cap_tam_ung`) = tiền đã thu về / lượt cấp là nhầm. Nếu thực chi vẫn
 * đứng nguyên thì con số ấy vĩnh viễn kẹt trong báo cáo, mà đơn thì đang nằm ở "Chờ cấp".
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */

VHCP_Don::go_cap_tam_ung( $MA2, 'Cấp nhầm đơn' );
teq( 'đơn 2 quay về Chờ cấp tạm ứng', 'Chờ cấp tạm ứng', (string) VHCP_Don::don_row( $MA2 )['trang_thai'] );
teq( '🔴 thực chi tụt lại theo', 0, (int) VHCP_Don::get_don( $MA2 )['tongCN']['thucChi'] );
teq( 'cờ daCapTien về 0', 0, (int) VHCP_Don::get_don( $MA2 )['daCapTien'] );
/* Còn lại: đơn 1 (1.700.000) + đơn 3 (400.000 cá nhân + 600.000 NCC) = 2.700.000. */
teq( 'và báo cáo tổng quan cũng tụt', 2700000, $tong_finance() );
teq( 'tra theo mã tài khoản cũng tụt', 2700000, $tong_trama() );
teq( 'báo cáo một gian/cơ sở cũng tụt', 2700000, $tong_gian() );
teq( 'chi phí vận hành theo tuần cũng tụt', 2700000, $tong_tuan() );
teq( '⚠️ nhưng tạm ứng vẫn ghi nhận nguyên (số đã duyệt không mất)',
	900000, (int) VHCP_Don::get_don( $MA2 )['tongCN']['tamUng'] );

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * KẾT
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */

if ( count( $truot ) ) {
	echo "\n=== LUẬT TẠM ỨNG TRƯỚC / THỰC CHI SAU ===\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat   TRƯỢT: " . count( $truot ) . "\n";
	exit( 1 );
}
echo "\n✓ SẠCH — $dat phép: trước khi cấp tiền chỉ ghi nhận TẠM ỨNG, cấp rồi mới tính THỰC CHI.\n";
