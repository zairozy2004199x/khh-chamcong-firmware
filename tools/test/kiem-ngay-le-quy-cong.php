<?php
/**
 * NGÀY LỄ (hệ số) VÀ QUY ĐỔI GIỜ RA CÔNG.
 *
 * =================================================================================================
 * 🔴 HAI LỖI BÀI NÀY SINH RA ĐỂ CANH
 * =================================================================================================
 * 1. Anh Thắng 18/09/2026: *"Cho anh hỏi chỗ set lịch lương lễ và ngày lễ, ngày đó x2 hay x3"*.
 *    Trước bản này KHÔNG CÓ chỗ nào: cơ sở tính theo giờ trả đúng một đơn giá cho mọi ngày.
 *
 * 2. Anh Thắng 19/09/2026: *"thiết lập lương cố định, anh đúng bằng công nó đủ lương thì hệ thống
 *    không hiểu"*, rồi *"nhân viên tính theo công tháng thì sẽ quy đổi theo 4 tiếng 1/2 công và
 *    8h là 1 công (bổ sung bảng set)"*. Lối cũ đếm SỐ NGÀY CÓ CHẤM: tạt vào hai tiếng rồi về
 *    cũng trọn một công, y như người làm mười hai tiếng.
 *
 * Bài này không hỏi "có màn khai không". Nó hỏi đúng thứ ra TIỀN: một ngày mấy giờ thì mấy công,
 * và một giờ ngày lễ thì cộng thêm bao nhiêu.
 *
 * Chạy: php tools/test/kiem-ngay-le-quy-cong.php
 */

$goc = dirname( dirname( __DIR__ ) );
require __DIR__ . '/wp-stub.php';
vhcc_test_boot( $goc . '/wordpress/vhcp-cham-cong' );

$dat = 0; $truot = array();
function t( $ten, $dk, $them = null ) {
	global $dat, $truot;
	if ( $dk ) { $dat++; return; }
	$truot[] = $ten . ( null === $them ? '' : "\n      → " . ( is_scalar( $them )
		? substr( (string) $them, 0, 400 ) : json_encode( $them, JSON_UNESCAPED_UNICODE ) ) );
}
function teq( $ten, $mong, $thuc ) {
	t( $ten . ' (mong ' . json_encode( $mong, JSON_UNESCAPED_UNICODE ) . ')', $mong === $thuc, $thuc );
}

$AD = array( 'name' => 'Sếp', 'role' => VHCC_Vai::ADMIN, 'ma_nv' => 'LEADMIN' );
$NV = array( 'name' => 'Em NV', 'role' => VHCC_Vai::NV, 'ma_nv' => 'LENV' );

/* ================================================================== 1. đọc ngày người ta gõ */

echo "— đọc ngày người ta gõ —\n";
teq( 'ISO đủ',              '2027-02-17', VHCC_NgayLe::chuan_ngay( '2027-02-17' ) );
teq( 'ISO thiếu số 0',      '2027-02-07', VHCC_NgayLe::chuan_ngay( '2027-2-7' ) );
teq( 'kiểu Việt dd/mm/yyyy','2027-02-17', VHCC_NgayLe::chuan_ngay( '17/02/2027' ) );
/* 🔴 HAI SỐ ĐỌC THEO KIỂU NGƯỜI VIỆT: NGÀY TRƯỚC, THÁNG SAU — rồi LƯU theo `MM-DD` cho khớp
   kho của `mtd_la_le()`. Đọc thẳng theo kho thì "2-9" thành mùng 9 tháng 2: mất hẳn Quốc
   khánh, mà trên màn vẫn hiện một dòng trông như đã khai xong. Đây là chỗ sai không báo gì. */
teq( '🔴 "2-9" là mùng 2 tháng 9 (Quốc khánh)', '09-02', VHCC_NgayLe::chuan_ngay( '2-9' ) );
teq( '   "02-09" cũng vậy',                     '09-02', VHCC_NgayLe::chuan_ngay( '02-09' ) );
teq( '   "30-4" là 30 tháng 4',                 '04-30', VHCC_NgayLe::chuan_ngay( '30-4' ) );
teq( '   "1/5" là mùng 1 tháng 5',              '05-01', VHCC_NgayLe::chuan_ngay( '1/5' ) );
/* Số thứ hai > 12 thì không thể là tháng — người ấy gõ kiểu `MM-DD`, đọc ngược lại. */
teq( '   "9-25" là 25 tháng 9', '09-25', VHCC_NgayLe::chuan_ngay( '9-25' ) );
teq( '🔴 ngày không có thật thì CHỐI, không đoán', '', VHCC_NgayLe::chuan_ngay( '2027-02-30' ) );
teq( 'chữ lung tung thì chối', '', VHCC_NgayLe::chuan_ngay( 'tết' ) );
teq( 'rỗng thì chối',          '', VHCC_NgayLe::chuan_ngay( '' ) );

/* ================================================================== 2. chưa khai = không đổi gì */

echo "— chưa khai thì hệ thống đứng im —\n";
t( '🔴 chưa khai gì thì KHÔNG chạy', ! VHCC_NgayLe::dang_chay() );
teq( '   và mọi ngày đều hệ số 1', 1.0, VHCC_NgayLe::he_so_cua( '2026-09-02' ) );

/* ================================================================== 3. khai và tra */

echo "— khai lịch, tra hệ số —\n";
$r = VHCC_NgayLe::them( $NV, '2-9', 'Quốc khánh' );
t( '🔴 nhân viên thường KHÔNG sửa được lịch lễ', empty( $r['ok'] ), $r );

t( 'admin thêm được', ! empty( VHCC_NgayLe::them( $AD, '2-9', 'Quốc khánh' )['ok'] ) );
t( 'đặt hệ số chung', ! empty( VHCC_NgayLe::dat_he_so( $AD, '2' )['ok'] ) );

t( '🔴 nay đã chạy', VHCC_NgayLe::dang_chay() );
teq( 'ngày lễ hằng năm ăn ×2', 2.0, VHCC_NgayLe::he_so_cua( '2026-09-02' ) );
teq( '   và cả năm sau nữa',   2.0, VHCC_NgayLe::he_so_cua( '2030-09-02' ) );
teq( 'ngày thường vẫn ×1',     1.0, VHCC_NgayLe::he_so_cua( '2026-09-03' ) );
teq( 'tên ngày tra ra được', 'Quốc khánh', VHCC_NgayLe::ten_cua( '2026-09-02' ) );

/* Hệ số riêng ĐÈ hệ số chung — Tết ×3 trong khi lễ thường ×2. */
t( 'thêm Tết có hệ số riêng', ! empty( VHCC_NgayLe::them( $AD, '2027-02-17', 'Mùng 1 Tết', '3' )['ok'] ) );
teq( '🔴 hệ số riêng đè hệ số chung', 3.0, VHCC_NgayLe::he_so_cua( '2027-02-17' ) );
teq( '   ngày lễ khác vẫn theo chung', 2.0, VHCC_NgayLe::he_so_cua( '2027-09-02' ) );

/* 🔴 ĐÚNG NGÀY THẮNG LẶP HẰNG NĂM. Khai 02-09 lặp hằng năm rồi khai riêng 2026-09-02 ×3 (năm
   nay công ty trả hơn) thì năm nay phải ăn ×3, không phải ×2. */
t( 'thêm một lần đè lên ngày lặp', ! empty( VHCC_NgayLe::them( $AD, '2026-09-02', 'QK 2026', '3' )['ok'] ) );
teq( '🔴 khớp đúng ngày thắng khớp hằng năm', 3.0, VHCC_NgayLe::he_so_cua( '2026-09-02' ) );
teq( '   năm khác vẫn theo dòng hằng năm',    2.0, VHCC_NgayLe::he_so_cua( '2028-09-02' ) );
t( 'xoá được', ! empty( VHCC_NgayLe::xoa( $AD, '2026-09-02' )['ok'] ) );
teq( '   xoá rồi thì về hệ số của dòng hằng năm', 2.0, VHCC_NgayLe::he_so_cua( '2026-09-02' ) );

/* Chặn gõ nhầm. */
t( '🔴 hệ số 0 thì chối', empty( VHCC_NgayLe::dat_he_so( $AD, '0' )['ok'] ) );
t( '🔴 hệ số 200 thì chối (thừa chữ số)', empty( VHCC_NgayLe::dat_he_so( $AD, '200' )['ok'] ) );
t( 'hệ số 1 thì NHẬN — đó là cách tắt', ! empty( VHCC_NgayLe::dat_he_so( $AD, '1' )['ok'] ) );
/* ⚠️ Hệ số chung về 1 mà vẫn còn một ngày khai hệ số RIÊNG thì tính năng VẪN chạy — đúng như
   thế, và đó là lý do `dang_chay()` phải soi từng dòng chứ không chỉ nhìn con số chung. */
t( '   vẫn chạy vì Tết còn hệ số riêng ×3', VHCC_NgayLe::dang_chay() );
VHCC_NgayLe::xoa( $AD, '2027-02-17' );
t( '🔴 bỏ nốt dòng riêng thì mới tắt hẳn', ! VHCC_NgayLe::dang_chay() );
VHCC_NgayLe::dat_he_so( $AD, '2' );
VHCC_NgayLe::them( $AD, '2027-02-17', 'Mùng 1 Tết', '3' );

/* ⚠️ Lịch chung phải chảy sang cả nhánh Máy Tự Động — hai bảng lương nói hai chuyện về cùng
   một ngày là thứ không ai lần ra được. */
t( '🔴 lịch chung chảy sang nhánh Máy Tự Động',
	VHCC_Luong::mtd_la_le( '2026-09-02', VHCC_Luong::mtd_ngay_le() ) );

/* ================================================================== 4. quy đổi giờ ra công */

echo "— quy đổi giờ ra công —\n";
$b = VHCC_QuyCong::bac();
t( 'mặc định có bậc', count( $b ) >= 2, $b );
teq( '🔴 8 giờ = 1 công',    1.0, VHCC_QuyCong::cong_cua_ngay( 8 ) );
teq( '🔴 4 giờ = 0,5 công',  0.5, VHCC_QuyCong::cong_cua_ngay( 4 ) );
teq( '   8,5 giờ vẫn 1 công', 1.0, VHCC_QuyCong::cong_cua_ngay( 8.5 ) );
/* 🔴 LÀM DƯ KHÔNG THÀNH CÔNG LẺ. "8h là 1 công" là câu về một ngày công đủ, không phải một tỉ
   lệ chạy tuyến tính — 12 giờ thành 1,5 công là tự đẻ ra tiền không ai duyệt. */
teq( '🔴 12 giờ VẪN 1 công, không phải 1,5', 1.0, VHCC_QuyCong::cong_cua_ngay( 12 ) );
teq( '   7,9 giờ rơi xuống bậc 4h', 0.5, VHCC_QuyCong::cong_cua_ngay( 7.9 ) );
teq( '🔴 3,9 giờ = 0 công, KHÔNG làm tròn lên', 0.0, VHCC_QuyCong::cong_cua_ngay( 3.9 ) );
teq( '   0 giờ = 0 công', 0.0, VHCC_QuyCong::cong_cua_ngay( 0 ) );

/* 🔴 CỘNG THEO TỪNG NGÀY. Gộp giờ cả tháng rồi mới quy đổi thì 26 ngày × 4 giờ (đáng ra 13
   công) thành 104 giờ, rơi vào bậc 8h, ra đúng 1 công. */
$thang = array();
for ( $i = 1; $i <= 26; $i++ ) { $thang[ sprintf( '2026-08-%02d', $i ) ] = 4; }
teq( '🔴 26 ngày × 4 giờ = 13 công, không phải 1', 13.0, VHCC_QuyCong::cong_cua_thang( $thang ) );

$tron = array( '2026-08-01' => 8, '2026-08-02' => 4, '2026-08-03' => 2, '2026-08-04' => 12 );
teq( 'trộn: 8 + 4 + 2 + 12 giờ → 1 + 0,5 + 0 + 1', 2.5, VHCC_QuyCong::cong_cua_thang( $tron ) );

/* ---- bảng set: đổi được, và chặn được mấy cách gõ hỏng ---- */
echo "— bảng set —\n";
t( '🔴 nhân viên thường KHÔNG sửa được bảng', empty( VHCC_QuyCong::dat( $NV, array( 8 ), array( 1 ) )['ok'] ) );

$r = VHCC_QuyCong::dat( $AD, array( '6', '3', '', '', '', '' ), array( '1', '0,5', '', '', '', '' ) );
t( 'admin đổi được bảng', ! empty( $r['ok'] ), $r );
teq( '   6 giờ nay là 1 công',   1.0, VHCC_QuyCong::cong_cua_ngay( 6 ) );
teq( '   4 giờ nay là 0,5 công', 0.5, VHCC_QuyCong::cong_cua_ngay( 4 ) );
teq( '   2 giờ vẫn 0 công',      0.0, VHCC_QuyCong::cong_cua_ngay( 2 ) );
t( '   và biết là không còn mặc định', ! VHCC_QuyCong::la_mac_dinh() );

/* 🔴 XẾP LẠI THEO GIỜ GIẢM DẦN, KHÔNG TIN THỨ TỰ NGƯỜI GÕ. Gõ bậc thấp lên trên mà máy đọc theo
   thứ tự ấy thì ngày làm 10 tiếng dừng ở nửa công. */
$r = VHCC_QuyCong::dat( $AD, array( '4', '8' ), array( '0.5', '1' ) );
t( 'gõ ngược thứ tự vẫn nhận', ! empty( $r['ok'] ), $r );
teq( '🔴 và 10 giờ vẫn ra 1 công', 1.0, VHCC_QuyCong::cong_cua_ngay( 10 ) );

$r = VHCC_QuyCong::dat( $AD, array( '8', '4' ), array( '0.5', '1' ) );
t( '🔴 bậc cao mà ăn ít hơn bậc thấp thì CHỐI', empty( $r['ok'] ), $r );
$r = VHCC_QuyCong::dat( $AD, array( '8', '8' ), array( '1', '0.5' ) );
t( '🔴 hai bậc cùng mốc giờ thì chối', empty( $r['ok'] ), $r );
$r = VHCC_QuyCong::dat( $AD, array( '8', '' ), array( '', '0.5' ) );
t( '🔴 bậc thiếu một ô thì chối', empty( $r['ok'] ), $r );
$r = VHCC_QuyCong::dat( $AD, array( '', '' ), array( '', '' ) );
t( '🔴 xoá sạch bảng thì chối — không thì mọi người ăn lương tháng nhận 0đ', empty( $r['ok'] ), $r );
$r = VHCC_QuyCong::dat( $AD, array( '30' ), array( '1' ) );
t( '🔴 bậc 30 giờ thì chối — không ngày nào chạm tới', empty( $r['ok'] ), $r );

t( 'về mặc định được', ! empty( VHCC_QuyCong::ve_mac_dinh( $AD )['ok'] ) );
teq( '   và 8 giờ lại là 1 công', 1.0, VHCC_QuyCong::cong_cua_ngay( 8 ) );
t( '   và biết là đang mặc định', VHCC_QuyCong::la_mac_dinh() );


/* =================================================================================================
 * 5. CHẠY THẬT QUA BẢNG LƯƠNG
 * =================================================================================================
 * Mấy phép trên chỉ soi hai cái máy tính riêng lẻ. Bài này gieo giờ chấm thật rồi gọi
 * `VHCC_BangLuong::dung()` — nơi tiền thật sự ra. Hai cái máy đúng mà nối sai vào bảng lương thì
 * vẫn trả sai, và đó đúng là chỗ không bài nào ở trên bắt được.
 * ------------------------------------------------------------------------------------------- */
echo "— chạy thật qua bảng lương —\n";

global $wpdb;
$U_KT = array( 'name' => 'Kế toán', 'role' => VHCC_Vai::KE_TOAN, 'ma_nv' => 'LEKT' );
$gieo = function ( $ma, $cs, $ngay, $vao_g, $so_phut ) use ( $wpdb ) {
	$wpdb->insert( VHCC_DB::t( 'cham_cong' ), array(
		'ma_nv' => $ma, 'ho_ten' => '', 'coso' => $cs, 'ngay' => $ngay,
		'gio_vao_giay' => $vao_g, 'gio_ra_giay' => $vao_g + (int) round( $so_phut * 60 ),
		'hau_to' => '', 'nguon' => 'may' ) );
};

VHCC_GiaGio::dat_coso( $U_KT, 'LE_SHOP', array( 'NV' => 20000 ) );
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'LE_GIO',
	'ho_ten' => 'Bạn Ăn Theo Giờ', 'cua_hang' => 'LE_SHOP', 'chuc_vu' => 'NV',
	'vai_tro' => 'Nhân viên', 'trang_thai_lam_viec' => 'Đang làm' ) );

/* 10 ngày × 8 giờ. Ngày 02-09 là Quốc khánh, đang để hệ số chung ×2. */
for ( $i = 1; $i <= 9; $i++ ) { $gieo( 'LE_GIO', 'LE_SHOP', sprintf( '2026-09-%02d', $i ), 8 * 3600, 480 ); }
$gieo( 'LE_GIO', 'LE_SHOP', '2026-09-10', 8 * 3600, 480 );

$b = VHCC_BangLuong::dung( 'LE_SHOP', '2026-09' );
t( 'bảng lương chạy', ! empty( $b['ok'] ), $b );
$d = $b['dong'][0];
teq( 'giờ tổng vẫn đủ 80 — giờ lễ KHÔNG bị tách khỏi giờ tổng', 80.0, $d['gio'] );
teq( '🔴 nhận ra 8 giờ rơi vào ngày lễ', 8.0, $d['gioLe'] );
/* 🔴 CỘNG PHẦN CHÊNH, KHÔNG TÍNH LẠI TRỌN GÓI. 8 giờ ấy đã được trả một lần trong 80 × 20.000;
   phụ trội chỉ là phần còn thiếu 8 × 20.000 × (2 − 1) = 160.000. Tính trọn gói
   8 × 20.000 × 2 = 320.000 là trả hai lần cho cùng mấy giờ ấy. */
teq( '🔴 phụ trội = giờ lễ × giá × (hệ số − 1)', 160000.0, $d['phuTroiLe'] );
teq( '🔴 lương chính = 80×20.000 + 160.000', 1760000.0, $d['luongChinh'] );

/* Tắt hệ số thì mọi thứ về đúng như trước bản này — không cơ sở nào đang chạy bị đổi tiền. */
VHCC_NgayLe::dat_he_so( $U_KT, '1' );
VHCC_NgayLe::xoa( $U_KT, '2027-02-17' );
$b0 = VHCC_BangLuong::dung( 'LE_SHOP', '2026-09' );
teq( '🔴 tắt hệ số thì lương về đúng 80×20.000', 1600000.0, $b0['dong'][0]['luongChinh'] );
teq( '   và không còn giờ lễ nào', 0.0, $b0['dong'][0]['gioLe'] );
VHCC_NgayLe::dat_he_so( $U_KT, '2' );

/* ---- người ăn LƯƠNG THÁNG: quy đổi giờ ra công ---- */
$wpdb->insert( VHCC_DB::t( 'nhan_vien' ), array( 'ma_nv' => 'LE_THANG',
	'ho_ten' => 'Bạn Ăn Lương Tháng', 'cua_hang' => 'LE_SHOP', 'chuc_vu' => 'NV',
	'vai_tro' => 'Nhân viên', 'trang_thai_lam_viec' => 'Đang làm' ) );
/* 20 ngày đủ 8 giờ (20 công) + 4 ngày chỉ 4 giờ (2 công) = 22 công, trên 24 NGÀY có chấm. */
for ( $i = 1; $i <= 20; $i++ ) { $gieo( 'LE_THANG', 'LE_SHOP', sprintf( '2026-10-%02d', $i ), 8 * 3600, 480 ); }
for ( $i = 21; $i <= 24; $i++ ) { $gieo( 'LE_THANG', 'LE_SHOP', sprintf( '2026-10-%02d', $i ), 8 * 3600, 240 ); }
VHCC_ChotLuong::dat_thang( $U_KT, 'LE_SHOP', '2026-10', 'LE_THANG', true, '6000000', '26' );

$bt = VHCC_BangLuong::dung( 'LE_SHOP', '2026-10' );
$dt = null;
foreach ( $bt['dong'] as $x ) { if ( 'LE_THANG' === $x['ma'] ) { $dt = $x; break; } }
t( 'tìm thấy dòng người ăn lương tháng', null !== $dt, $bt['dong'] );
teq( 'đúng là lối theo tháng', 'thang', $dt['cheDo'] );
/* 🔴 ĐÂY LÀ CHỐT CHÍNH CỦA CẢ BÀI. Lối cũ đếm SỐ NGÀY CÓ CHẤM: 24 ngày → 24 công, tức mấy hôm
   chỉ làm 4 tiếng vẫn ăn trọn một công. Nay 20 + 0,5×4 = 22. */
teq( '🔴 số công thực quy đổi từ giờ, không đếm đầu ngày', 22.0, $dt['congThuc'] );
teq( '   nhưng SỐ NGÀY vẫn là 24 — hai con số khác nhau, đừng trộn', 24, $dt['soNgay'] );
teq( '🔴 lương = 6.000.000 × 22 ÷ 26', round( 6000000 * 22 / 26, 2 ), $dt['luongChinh'] );

/* ⚠️ NGƯỜI ĂN LƯƠNG THÁNG KHÔNG CÓ PHỤ TRỘI LỄ — lương của họ không có đơn giá giờ để nhân,
   và một ngày lễ đã nằm sẵn trong tháng lương ấy. */
VHCC_NgayLe::them( $U_KT, '5-10', 'Ngày thử' );
$bt2 = VHCC_BangLuong::dung( 'LE_SHOP', '2026-10' );
foreach ( $bt2['dong'] as $x ) { if ( 'LE_THANG' === $x['ma'] ) { $dt = $x; break; } }
teq( '🔴 người ăn lương tháng KHÔNG có phụ trội lễ', 0.0, $dt['phuTroiLe'] );
teq( '   và lương vẫn y nguyên', round( 6000000 * 22 / 26, 2 ), $dt['luongChinh'] );


/* =================================================================================================
 * 6. MÀN KHAI VIẾT NGÀY RA CHO NGƯỜI ĐỌC, KHÔNG IN KHOÁ TRONG KHO
 * =================================================================================================
 * Kho lưu `MM-DD`, người ta gõ `DD-MM`. In thẳng khoá ra bảng thì họ gõ "2-9" rồi thấy "09-02"
 * hiện ngay dưới, kết luận máy hiểu nhầm, và đi "sửa lại cho đúng" — tức làm hỏng thật một dòng
 * vốn đang đúng. Bài này soi chính cái bảng ấy.
 * ------------------------------------------------------------------------------------------- */
echo "— màn khai —\n";
$m_le = new ReflectionMethod( 'VHCC_Web', 'the_ngay_le' );
$m_le->setAccessible( true );
ob_start(); $m_le->invoke( null, 'KYTHU', $AD ); $h_le = ob_get_clean();

t( 'khối lịch lễ có vẽ ra', false !== strpos( $h_le, 'name="le_ngay"' ), substr( $h_le, 0, 200 ) );
t( '🔴 ngày 2/9 hiện là "2/9", KHÔNG phải khoá "09-02"',
	false !== strpos( $h_le, '2/9 — hằng năm' ) && false === strpos( $h_le, '>09-02' ), $h_le );
t( '🔴 ngày một lần hiện kiểu Việt 17/02/2027',
	false !== strpos( $h_le, '17/02/2027' ) && false === strpos( $h_le, '>2027-02-17<' ), $h_le );
t( '   ví dụ trong lời hướng dẫn cũng viết ngày trước tháng sau',
	false !== strpos( $h_le, '<b>2-9</b>' ), $h_le );
t( '   hệ số riêng của Tết hiện ×3', false !== strpos( $h_le, '×3' ), $h_le );

$m_qc = new ReflectionMethod( 'VHCC_Web', 'the_quy_cong' );
$m_qc->setAccessible( true );
ob_start(); $m_qc->invoke( null, 'KYTHU', $AD ); $h_qc = ob_get_clean();
t( 'khối quy đổi có vẽ ra', false !== strpos( $h_qc, 'name="qc_gio[]"' ), substr( $h_qc, 0, 200 ) );
t( '   và nói ra bảng đang dùng', false !== mb_strpos( $h_qc, '8h → 1 công' ), $h_qc );

echo "\n";
if ( $truot ) {
	echo '🔴 HỎNG ' . count( $truot ) . " phép thử:\n";
	foreach ( $truot as $x ) { echo '  ✗ ' . $x . "\n"; }
	echo "ĐẠT: $dat\n";
	exit( 1 );
}
echo "✓ ĐẠT: $dat phép thử — lịch lễ, hệ số, và quy đổi giờ ra công.\n";
